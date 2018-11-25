<?php

namespace Datashot\Mysql;

use Datashot\Lang\Observable;
use PDO;
use RuntimeException;

class MysqlDatabaseSnapper
{
    use Observable;

    /** @var MysqlDumperConfig */
    private $conf;

    /**
     * @var EventBus
     */
    private $bus;

    /**
     * @var MysqlDumpFileWriter
     */
    private $output;

    public function __construct($config = [])
    {
        $this->checkPreconditions();

        $this->conf = new MysqlDumperConfig($config);
    }

    public function config(array $config) {
        $this->conf->append($config);
    }

    public function where(...$args)
    {
        if (count($args) == 1) {

            if (is_array($args[0])) {
                foreach ($args[0] as $table => $where) {
                    $this->where($table, $where);
                }
                return;
            }

            $this->setFallbackWhere($args[0]);
        } else {
            $table = $args[0];
            $where = $args[1];
            $this->setWhereClause($table, $where);
        }

        return $this;
    }

    private function setFallbackWhere($string)
    {
        $this->conf->setFallbackWhere($this->wrap($string));
    }

    private function setWhereClause($table, $where)
    {
        $this->conf->setWhereClause($table, $this->wrap($where));
    }

    private function wrap($where)
    {
        if (is_callable($where)) {
            return $where;
        } else {
            return function () use ($where) {
                return $this->toString($where);
            };
        }
    }

    private function toString($value)
    {
        if ($value === true) {
            return 'true';
        }

        if ($value === false) {
            return 'false';
        }

        return strval($value);
    }

    const DATABASE_TIMEOUT = 60 * 60 * 16;

    const DUMPING_SCHEMA     = 'dumping_schema';
    const DUMPING_TABLE      = 'dumping_table';
    const TABLE_DUMPED       = 'table_dumped';
    const SNAPED             = 'snaped';
    const DUMPING_ACTIONS    = 'dumping_actions';
    const CREATING_SNAP_FILE = 'creating_snap_file';
    const SNAPPING           = 'snapping';
    const APPENDING          = 'appending';
    const APPENDED           = 'appended';

    /** @var PDO */
    private $pdo;

    /** @var string */
    private $snapfile;

    /** @var string */
    private $connectionFile;

    public function snap()
    {
        $this->snapping();

        $this->touchOutput();

        $this->connect();

        // $this->setupConnectionFile();

        $start = microtime(true);

        if ($this->dumpAll()) {
            # dumping database schema
            $this->dumpTablesDdl();
            $this->dumpViews();
        }

        if ($this->dumpData()) {
            $this->dumpTables();
        }

        $this->output->close();

        // if ($this->dumpAll()) {
        //    $this->dumpActions();
        //}

        $end = microtime(true);

        $this->snapped($start, $end);
    }

    private function connect()
    {
        $dsn = "mysql:host={$this->conf->host};" .
               "port={$this->conf->port};" .
               "dbname={$this->conf->database}";

        $this->pdo = new PDO($dsn, $this->conf->username, $this->conf->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_TIMEOUT => static::DATABASE_TIMEOUT
        ]);
    }

    private function dumpTablesDdl()
    {
        $this->eachTable(function ($table) {
            $this->dumpTableDdl($table);
        });
    }

    private function dumpViews()
    {
        $this->eachView(function ($view) {
            $this->dumpViewDdl($view);
        });
    }

    private function dumpActions()
    {
        $this->notify(static::DUMPING_ACTIONS);
        $this->appendMsg("Restaurando rotinas, triggers e functions...");
        $this->appendOutput("
            /usr/bin/mysqldump --defaults-file={$this->connectionFile} \
                --no-create-info \
                --no-data \
                --routines \
                --triggers \
                --single-transaction \
                --lock-tables=false \
                {$this->conf->database}
        ");
    }

    private function eachTable($closure)
    {
        $results = $this->pdo->query("
            SELECT table_name
            FROM information_schema.tables
            where table_schema='{$this->conf->database}' AND
                  table_type = 'BASE TABLE'
        ");

        foreach ($results as $res) {

            $table = $res->table_name;

            call_user_func($closure, $table);
        }
    }

    private function eachView($closure)
    {
        $results = $this->pdo->query("
            SELECT table_name
            FROM information_schema.tables
            where table_schema='{$this->conf->database}' AND
                  table_type = 'VIEW'
        ");

        foreach ($results as $res) {

            $view = $res->table_name;

            call_user_func($closure, $view);
        }
    }

    private function dump($table, $whereClause = NULL)
    {
        if (!empty($whereClause)) {
            $whereClause = $this->strip($whereClause);
        }

        $this->notify(static::DUMPING_TABLE, [
            'table' => $table,
            'where' => $whereClause
        ]);

        if (empty($whereClause)) {
            $whereClause = "";
        } else {
            $whereClause = "--where=\"{$whereClause}\"";
        }

        $start = microtime(true);


        $this->appendMsg("Restaurando {$table}...");
        $this->appendOutput("
            /usr/bin/mysqldump --defaults-file={$this->connectionFile} \
                --no-create-info \
                --no-tablespaces \
                --skip-triggers \
                --disable-keys \
                --no-autocommit \
                --single-transaction \
                --lock-tables=false \
                --quick \
                {$whereClause} \
                {$this->conf->database} {$table}
        ");

        $end = microtime(true);

        $this->notify(static::TABLE_DUMPED, [
            'table' => $table,
            'execution_time' => ($end - $start)
        ]);
    }

    private function appendOutput($command)
    {
        if (is_array($command)) {
            $command = implode(" ", $command);
        }

        $command = trim($command);

        $this->exec("
            {$command} \
            | sed -E 's/DEFINER=`[^`]+`@`[^`]+`/DEFINER=CURRENT_USER/g' \
            | gzip >> {$this->snapfile}
        ");
    }

    private function exec($command)
    {
        $return_var = 1;
        $stdout = [];
        exec(" ( {$command} ) 2>&1 ", $stdout, $return_var);

        if ($return_var !== 0 || count($stdout) > 0) {
            throw new RuntimeException(
                "Problemas na execução do comando! \n  " .
                implode("\n  ", $stdout)
            );
        }
    }

    private function buildWhereClause($table)
    {
        $builder = $this->lookupBuilder($table);

        return call_user_func($builder, $this->pdo, $this->conf);
    }

    private function lookupBuilder($table)
    {
        $whereBuilders = $this->conf->get('wheres', []);

        if (isset($whereBuilders[$table])) {
            $where = $whereBuilders[$table];

            return $this->wrap($where);
        } else {
            return $this->wrap($this->conf->get('where', 'true'));
        }
    }

    private function setupConnectionFile()
    {
        register_shutdown_function(function () {
            // Garante o clean-up do arquivo de conexão
            @unlink($this->connectionFile);
        });

        $this->connectionFile = $this->genTempFilePath();

        $temp = $this->throwIfFails(fopen($this->connectionFile, 'w'),
            "Não foi possível criar o arquivo de conexão " .
            "\"{$this->connectionFile}\""
        );

        $res = fwrite($temp,
            "[mysqldump]\n" .
            "host={$this->conf->host}\n" .
            "port={$this->conf->port}\n" .
            "user={$this->conf->username}\n" .
            "password={$this->conf->password}\n"
        );

        if ($res === FALSE) {
            throw new RuntimeException(
                "Não foi possível escrever o arquivo de conexão " .
                "\"{$this->connectionFile}\""
            );
        }

        $this->throwIfFails(fflush($temp),
            "Não foi possível escrever o arquivo de conexão " .
            "\"{$this->connectionFile}\""
        );

        $this->throwIfFails(fclose($temp),
            "Não foi possível fechar o arquivo de conexão " .
            "\"{$this->connectionFile}\""
        );
    }

    private function throwIfFails($result, $errmsg)
    {
        if ($result === FALSE) {
            throw new RuntimeException($errmsg);
        }

        return $result;
    }

    private function genTempFilePath()
    {
        return tempnam(sys_get_temp_dir(), '.my.cnf.');
    }

    private function strip($whereClause)
    {
        $whereClause = str_replace("\n", "", $whereClause);
        return preg_replace("/[ ]{2,}/", ' ', $whereClause);
    }

    private function dumpAll()
    {
        return $this->conf->dumpAll();
    }

    private function dumpData()
    {
        return $this->conf->dumpData();
    }

    private function dumpTables()
    {
        $this->eachTable(function ($table) {
            $this->dumpTableData($table);
        });
    }

    private function snapping()
    {
        $this->notify(static::SNAPPING, $this->conf);
    }

    private function snapped($start, $end)
    {
        $this->notify(static::SNAPED, [
            'execution_time' => ($end - $start)
        ]);
    }

    private function checkPreconditions()
    {
        if (!$this->commandExists('mysqldump')) {
            throw new RuntimeException(
                "Comando \"mysqldump\" não encontrado!"
            );
        }

        if (!$this->commandExists('gzip')) {
            throw new RuntimeException(
                "Comando \"gzip\" não encontrado!"
            );
        }
    }

    private function commandExists($command)
    {
        $return_var = 1;
        $stdout = [];

        exec(" ( command -v {$command} > /dev/null 2>&1 ) 2>&1 ", $stdout, $return_var);

        return $return_var === 0;
    }

    public function append($string)
    {
        $this->notify(static::APPENDING, [
            'string' => $string
        ]);

        $file = gzopen($this->snapfile, 'a');

        if ($file === FALSE) {
            throw new RuntimeException(
                "Arquivo \"{$this->snapfile}\" não pode ser aberto!"
            );
        }

        $this->throwIfFails(gzwrite($file, $string),
            "Falha na escrita do arquivo \"{$this->snapfile}\"!"
        );

        $this->throwIfFails(gzclose($file),
            "Falha no fechamento do arquivo \"{$this->snapfile}\"!"
        );

        $this->notify(static::APPENDED);
    }

    private function appendMsg($string)
    {
        $this->append("\nSELECT \"{$string}\";\n");
    }

    private function dumpTableDdl($table)
    {
        $ddl = $this->getCreateTableDdl($table);

        $this->output->comment("ddl for \"{$table}\" table ");
        $this->output->message("Creating table {$table}...");
        $this->output->command($ddl);
        $this->output->newLine(2);
    }

    private function dumpViewDdl($table)
    {
        $ddl = $this->getCreateViewDdl($table);

        $this->output->comment("ddl for \"{$table}\" view");
        $this->output->message("Creating view {$table}...");
        $this->output->command($ddl);
        $this->output->newLine(2);
    }

    private function getCreateTableDdl($table)
    {
        $res = $this->first("SHOW CREATE TABLE {$table}");

        if ($res == FALSE) {
            throw new RuntimeException(
                "Can't get create table ddl for \"{$table}\" table"
            );
        }

        return $res['Create Table'];
    }

    private function getCreateViewDdl($table)
    {
        $res = $this->first("SHOW CREATE VIEW {$table}");

        if ($res == FALSE) {
            throw new RuntimeException(
                "Can't get create table ddl for \"{$table}\" view"
            );
        }

        return $this->removeDefiner($res['Create View']);
    }

    private function first($query)
    {
        $stmt = $this->pdo->query($query);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function touchOutput()
    {
        $this->output = $this->conf->getOutputFile();
    }

    private function dumpTableData($table)
    {
        $where = $this->buildWhereClause($table);
        $columns = implode(", ", $this->getColumns($table));

        $stmt = $this->pdo->query("SELECT {$columns} FROM `{$table}` WHERE {$where}");

        $this->output->comment("Dumping data for table \"{$table}\"");
        $this->output->comment("  WHERE {$where}");
        $this->output->message("Restoring {$table}...");
        $this->output->command("ALTER TABLE `{$table}` DISABLE KEYS");
        $this->output->command("SET autocommit = 0");

        $first = TRUE;

        foreach ($stmt as $row) {

            if ($first) {

                $this->output->writeln("INSERT INTO `{$table}` ({$columns}) VALUES ");

                $first = FALSE;

            } else {
                $this->output->write(', ');
            }

            $values = $this->toValues($table, $row);

            $this->output->write('(' . implode(", ", $values) . ')');
        }

        if ($stmt->rowCount() > 0) {
            $this->output->writeln(";");
        }

        $stmt->closeCursor();

        $this->output->command("ALTER TABLE `{$table}` ENABLE KEYS");
        $this->output->command("COMMIT");
        $this->output->newLine(2);
    }

    private function toValues($table, $row)
    {
        if ($this->conf->hasRowTransformer($table)) {
            $transformer = $this->conf->getRowTransformer($table);

            call_user_func($transformer, $row);
        }

        $columnTypes = $this->getColumnTypes($table);

        $values = [];

        foreach ($row as $colName => $colValue) {
            $values[] = $this->escape($colValue, $columnTypes[$colName]);
        }

        return $values;
    }

    /**
     * @param $value mixed
     * @param $type MysqlColumnType
     */
    private function escape($value, $type)
    {
        if (is_null($value)) {
            return "NULL";
        } elseif ($type->isBlob()) {
            if ($type->is('bit') || !empty($value)) {
                return "0x${value}";
            } else {
                return "''";
            }
        } elseif ($type->isNumeric()) {
            return $value;
        }

        return $this->pdo->quote($value);
    }

    /**
     * @return MysqlColumnType[]
     */
    private function getColumnTypes($table)
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");

        $types = [];

        foreach ($stmt as $row) {
            $types[$row->Field] = MysqlColumnType::fromRow($row);
        }

        return $types;
    }

    private function getColumns($table)
    {
        $columns = [];

        $columnsTypes = $this->getColumnTypes($table);

        foreach ($columnsTypes as $colName => $colType) {
            if ($colType->is('bit') && $this->dumpSettings['hex-blob']) {
                $columns[] = "LPAD(HEX(`${colName}`),2,'0') AS `${colName}`";
            } else if ($colType->isBlob() && $this->dumpSettings==['hex-blob']) {
                $columns[] = "HEX(`${colName}`) AS `${colName}`";
            } else if ($colType->isVirtual()) {
                $this->dumpSettings['complete-insert'] = true;
                continue;
            } else {
                $columns[] = "`${colName}`";
            }
        }

        return $columns;
    }

    private function removeDefiner($command)
    {
        return preg_replace("/DEFINER=`[^`]+`@`[^`]+`/", 'DEFINER=CURRENT_USER', $command);
    }
}
