<?php

namespace Datashot\Mysql;

use Datashot\Core\DatabaseServer;
use Datashot\Core\DatabaseSnapper;
use Datashot\Core\SnapperConfiguration;
use Datashot\Datashot;
use Datashot\Lang\DataBag;
use Datashot\Util\EventBus;
use PDO;
use RuntimeException;

class MysqlDatabaseSnapper implements DatabaseSnapper
{
    const DATABASE_TIMEOUT = 60 * 60 * 16;

    private static $EVENTS = [
        DatabaseSnapper::SNAPED,
    ];

    /** @var SnapperConfiguration */
    private $conf;

    /**
     * @var EventBus
     */
    private $bus;

    /**
     * @var MysqlDumpFileWriter
     */
    private $output;

    /** @var PDO */
    private $pdo;

    /** @var string */
    private $snapfile;

    /** @var string */
    private $connectionFile;

    public function __construct(EventBus $bus, SnapperConfiguration $conf)
    {
        $this->bus = $bus;
        $this->conf = $conf;

        $this->processEventHooks($conf);

        $this->checkPreconditions();
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

    public function snap()
    {
        $this->snapping();

        $this->connect();

        $this->setupConnectionFile();

        $this->touchOutput();

        $this->flushFileHeader();

        $start = microtime(true);

        if ($this->dumpAll()) {
            # dumping database schema
            $this->dumpTablesDdl();
            $this->dumpViews();
        }

        if ($this->dumpData()) {
            $this->dumpTables();
        }

        if ($this->dumpAll()) {
            $this->dumpActions();
        }

        $this->endStandardServerSettings();

        $end = microtime(true);

        $this->snapped($start, $end);

        $this->output->close();
    }

    private function connect()
    {
        $dsn = "mysql:host={$this->conf->getHost()};" .
               "port={$this->conf->getPort()};" .
               "dbname={$this->conf->getDatabaseName()}";

        $this->pdo = new PDO($dsn, $this->conf->getUser(), $this->conf->getPassword(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_TIMEOUT => static::DATABASE_TIMEOUT,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]);
    }

    private function dumpTablesDdl()
    {
        $this->publish(DatabaseSnapper::DUMPING_DDL);

        $this->eachTable(function ($table) {
            $this->dumpTableDdl($table);
        });
    }

    private function dumpViews()
    {
        $this->publish(DatabaseSnapper::DUMPING_VIEWS);

        $this->eachView(function ($view) {
            $this->dumpViewDdl($view);
        });
    }

    private function eachTable($closure)
    {
        $results = $this->pdo->query("
            SELECT table_name
            FROM information_schema.tables
            where table_schema='{$this->conf->getDatabaseName()}' AND
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
            where table_schema='{$this->conf->getDatabaseName()}' AND
                  table_type = 'VIEW'
        ");

        foreach ($results as $res) {

            $view = $res->table_name;

            call_user_func($closure, $view);
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
        $this->publish(DatabaseSnapper::DUMPING_DATA);

        $this->eachTable(function ($table) {
            $this->dumpTableData($table);
        });
    }

    private function snapping()
    {
        $this->publish(DatabaseSnapper::SNAPPING);
    }

    private function snapped($start, $end)
    {
        $this->publish(DatabaseSnapper::SNAPED, [
            'time' => ($end - $start)
        ]);
    }

    public function append($command)
    {
        $this->output->writeln($command);
    }

    private function dumpTableDdl($table)
    {
        $this->publish(DatabaseSnapper::DUMPING_TABLE_DDL, [ 'table' => $table ]);

        $ddl = $this->getCreateTableDdl($table);

        $this->output->comment("DDL for \"{$table}\" table ");
        $this->output->message("Creating table {$table}...");
        $this->output->command($ddl);
        $this->output->newLine(2);
    }

    private function dumpViewDdl($view)
    {
        $this->publish(DatabaseSnapper::DUMPING_VIEW, [ 'view' => $view ]);

        $ddl = $this->getCreateViewDdl($view);

        $this->output->comment("DDL for \"{$view}\" view");
        $this->output->message("Creating view {$view}...");
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
        $this->publish(DatabaseSnapper::CREATING_SNAP_FILE);

        $this->snapfile = $this->conf->getOutputFilePath();

        $this->output = new MysqlDumpFileWriter($this->snapfile);

        $this->output->open();
    }

    private function dumpTableData($table)
    {
        if ($this->conf->hasRowTransformer($table)) {
            $this->dumpTableDataViaPhp($table);
        } else {
            $this->dumpTableDataViaMysqlClient($table);
        }
    }

    private function toValues($table, $row)
    {
        if ($this->conf->hasRowTransformer($table)) {
            $transformer = $this->conf->getRowTransformer($table);

            $row = call_user_func($transformer, $row);
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

    private function dumpTriggers()
    {
        $this->publish(DatabaseSnapper::DUMPING_TRIGGERS);

        $this->eachTriggers(function ($trigger) {
            $this->dumpTrigger($trigger);
        });
    }

    private function eachTriggers($closure)
    {
        $results = $this->pdo->query("
            SHOW TRIGGERS FROM `{$this->conf->getDatabaseName()}`
        ");

        foreach ($results as $res) {

            $trigger = $res->Trigger;

            call_user_func($closure, $trigger);
        }
    }

    private function dumpTrigger($trigger)
    {
        $this->publish(DatabaseSnapper::DUMPING_TRIGGER, [
            'trigger' => $trigger
        ]);

        $cmd = $this->getCreateTigger($trigger);

        $this->output->comment("Trigger {$trigger}");
        $this->output->message("Creating {$trigger} trigger...");
        $this->output->command("DROP TRIGGER IF EXISTS `{$trigger}`");
        $this->output->writeln($cmd);
        $this->output->newLine(2);
    }

    private function getCreateTigger($trigger)
    {
        $row = $this->first("SHOW CREATE TRIGGER `{$trigger}`");

        $statement = $row['SQL Original Statement'];

        $statement = $this->removeDefiner($statement);

        return "DELIMITER ;;" . PHP_EOL . $statement . ";;" . PHP_EOL .
               "DELIMITER ;";
    }

    private function dumpProcedures()
    {
        $this->publish(DatabaseSnapper::DUMPING_PROCEDURES);

        $this->eachProcedures(function ($procedure) {
            $this->dumpProcedure($procedure);
        });
    }

    private function eachProcedures($closure)
    {
        $results = $this->pdo->query("
            SELECT SPECIFIC_NAME AS procedure_name
            FROM INFORMATION_SCHEMA.ROUTINES
            WHERE ROUTINE_TYPE='PROCEDURE' AND ROUTINE_SCHEMA='{$this->conf->getDatabaseName()}'
        ");

        foreach ($results as $res) {

            $procedure = $res->procedure_name;

            call_user_func($closure, $procedure);
        }
    }

    private function dumpProcedure($procedure)
    {
        $this->publish(DatabaseSnapper::DUMPING_PROCEDURE, [
            'procedure' => $procedure
        ]);

        $cmd = $this->getCreateProcedure($procedure);

        $this->output->comment("Procedure '{$procedure}'");
        $this->output->message("Creating {$procedure} procedure...");
        $this->output->command("DROP PROCEDURE IF EXISTS `{$procedure}`");
        $this->output->writeln($cmd);
        $this->output->newLine(2);
    }

    private function getCreateProcedure($procedure)
    {
        $row = $this->first("SHOW CREATE PROCEDURE `{$procedure}`");

        $statement = $row['Create Procedure'];

        $statement = $this->removeDefiner($statement);

        return "DELIMITER ;;" . PHP_EOL . $statement . ";;" . PHP_EOL .
               "DELIMITER ;";
    }

    private function dumpFunctions()
    {
        $this->publish(DatabaseSnapper::DUMPING_FUNCTIONS);

        $this->eachFunctions(function ($function) {
            $this->dumpFunction($function);
        });
    }

    private function eachFunctions($closure)
    {
        $results = $this->pdo->query("
            SELECT SPECIFIC_NAME AS function_name
            FROM INFORMATION_SCHEMA.ROUTINES
            WHERE ROUTINE_TYPE='FUNCTION' AND ROUTINE_SCHEMA='{$this->conf->getDatabaseName()}'
        ");

        foreach ($results as $res) {

            $function = $res->function_name;

            call_user_func($closure, $function);
        }
    }

    private function dumpFunction($function)
    {
        $this->publish(DatabaseSnapper::DUMPING_FUNCTION, [
            'function' => $function
        ]);

        $cmd = $this->getCreateFunction($function);

        $this->output->comment("Function '{$function}'");
        $this->output->message("Creating {$function} function...");
        $this->output->command("DROP FUNCTION IF EXISTS `{$function}`");
        $this->output->writeln($cmd);
        $this->output->newLine(2);
    }

    private function getCreateFunction($function)
    {
        $row = $this->first("SHOW CREATE FUNCTION `{$function}`");

        $statement = $row['Create Function'];

        $statement = $this->removeDefiner($statement);

        return "DELIMITER ;;" . PHP_EOL . $statement . ";;" . PHP_EOL .
               "DELIMITER ;";
    }

    private function flushFileHeader()
    {
        $this->output->comment("");
        $this->output->comment("Database dump taked via ". Datashot::getPackageName() ." v" . Datashot::getVersion());
        $this->output->comment("  " . Datashot::getPackageUrl());
        $this->output->comment("");
        $this->output->comment("{$this->conf->getDatabaseName()} from {$this->conf->getDatabaseServer()} server ");
        $this->output->comment("timestamp " . date("Y-m-d h:i:s"));
        $this->output->comment("");

        $this->output->newLine();

        $this->beginStandardServerSettings();

        $this->output->newLine();
    }

    private function cutoff($string, $maxLenght)
    {
        if (strlen($string) > $maxLenght) {
            return substr($string, 0, $maxLenght - 3 ) . '...';
        }

        return $string;
    }

    private function processEventHooks(SnapperConfiguration $config)
    {
        foreach (static::$EVENTS as $event) {
            // Check if the config array has a event-based hook
            if ($config->hasClosure($event)) {
                $this->on($event, $config->get($event));
            }
        }
    }

    private function beginStandardServerSettings()
    {
        $this->output->comment("Standard dump settings");
        $this->output->command("SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT");
        $this->output->command("SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS");
        $this->output->command("SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION");
        $this->output->command("SET NAMES utf8");


        $this->output->command("SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0");
        $this->output->command("SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0");

        $this->output->command("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

        $this->output->command("SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0");
    }

    private function endStandardServerSettings()
    {
        $this->output->comment("Restore old settings");
        $this->output->command("SET SQL_MODE=@OLD_SQL_MODE");
        $this->output->command("SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS");
        $this->output->command("SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS");
        $this->output->command("SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT");
        $this->output->command("SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS");
        $this->output->command("SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION");
        $this->output->command("SET SQL_NOTES=@OLD_SQL_NOTES");
        $this->output->newLine(2);
    }

    private function dumpActions()
    {
        $this->output->comment("Restoring actions");
        $this->dumpTriggers();
        $this->dumpProcedures();
        $this->dumpFunctions();
    }

    private function publish($event, array $data = [])
    {
        $this->bus->publish($event, $this, new DataBag($data));
    }

    private function dumpTableDataViaPhp($table)
    {
        $where = $this->buildWhereClause($table);
        $columns = implode(", ", $this->getColumns($table));

        $stmt = $this->pdo->query("
          SELECT {$columns} FROM `{$table}` WHERE {$where}
        ");


        $this->publish(DatabaseSnapper::DUMPING_TABLE_DATA, [
            'table' => $table,
            'where' => $where
        ]);

        $this->output->comment("Dumping data for table \"{$table}\"");
        $this->output->comment(" WHERE {$this->cutoff($where, 68)}");
        $this->output->message("Restoring {$table}...");

        // Optmization for faster bulky INSERT
        $this->output->command("LOCK TABLES `{$table}` WRITE");
        $this->output->command("ALTER TABLE `{$table}` DISABLE KEYS");
        $this->output->command("SET autocommit = 0");

        $first = TRUE;

        $start = microtime(true);

        foreach ($stmt as $row) {

            if ($first) {

                $this->output->writeln("INSERT INTO `{$table}` ({$columns}) VALUES ");

                $first = FALSE;

            } else {
                $this->output->write(",\n");
            }

            $values = $this->toValues($table, $row);

            $this->output->write('(' . implode(",", $values) . ')');
        }

        if ($stmt->rowCount() > 0) {
            $this->output->writeln(";");
        }

        $stmt->closeCursor();

        $this->output->command("ALTER TABLE `{$table}` ENABLE KEYS");
        $this->output->command("UNLOCK TABLES");
        $this->output->command("COMMIT");
        $this->output->newLine(2);

        $end = microtime(true);

        $this->publish(DatabaseSnapper::TABLE_DUMPED, [
            'time' => ($end - $start),
            'rows' => $stmt->rowCount()
        ]);
    }

    private function dumpTableDataViaMysqlClient($table)
    {
        $where = $this->buildWhereClause($table);

        if (!empty($where)) {
            $where = $this->strip($where);
        }

        $this->publish(DatabaseSnapper::DUMPING_TABLE_DATA, [
            'table' => $table,
            'where' => $where
        ]);

        $this->output->comment("Dumping data for table \"{$table}\"");
        $this->output->comment(" WHERE {$this->cutoff($where, 68)}");
        $this->output->message("Restoring {$table}...");

        if (empty($whereClause)) {
            $whereClause = "";
        } else {
            $whereClause = "--where=\"{$whereClause}\"";
        }

        $start = microtime(true);

        // Close and flush the current writings for mysql client
        // shell appending
        $this->output->close();

        $this->appendOutput("
            /usr/bin/mysqldump --defaults-file={$this->connectionFile} \
                --no-create-info \
                --no-tablespaces \
                --skip-triggers \
                --disable-keys \
                --no-autocommit \
                --single-transaction \
                --lock-tables=false \
                --skip-comments \
                --quick \
                {$whereClause} \
                {$this->conf->getDatabaseName()} {$table}
        ");

        $end = microtime(true);

        $this->publish(DatabaseSnapper::TABLE_DUMPED, [
            'time' => ($end - $start)
        ]);
    }

    private function strip($whereClause)
    {
        $whereClause = str_replace("\n", "", $whereClause);
        return preg_replace("/[ ]{2,}/", ' ', $whereClause);
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
                "Troubles executing the command! \n  " .
                implode("\n  ", $stdout)
            );
        }
    }

    private function setupConnectionFile()
    {
        register_shutdown_function(function () {
            // connection file clean-up
            @unlink($this->connectionFile);
        });

        $this->connectionFile = $this->genTempFilePath();

        if (($temp = fopen($this->connectionFile, 'w')) === FALSE) {
            throw new RuntimeException(
                "Não foi possível criar o arquivo de conexão " .
                "\"{$this->connectionFile}\""
            );
        }

        $res = fwrite($temp,
            "[mysqldump]\n" .
            "host={$this->conf->getHost()}\n" .
            "port={$this->conf->getPort()}\n" .
            "user={$this->conf->getUser()}\n" .
            "password={$this->conf->getPassword()}\n"
        );

        if ($res === FALSE) {
            throw new RuntimeException(
                "Não foi possível escrever o arquivo de conexão " .
                "\"{$this->connectionFile}\""
            );
        }

        if (fflush($temp) === FALSE) {
            throw new RuntimeException(
                "Não foi possível escrever o arquivo de conexão " .
                "\"{$this->connectionFile}\""
            );
        }

        if (fclose($temp) === FALSE) {
            throw new RuntimeException(
                "Não foi possível fechar o arquivo de conexão " .
                "\"{$this->connectionFile}\""
            );
        }
    }

    private function genTempFilePath()
    {
        return tempnam(sys_get_temp_dir(), '.my.cnf.');
    }

    /**
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->conf->getDatabaseName();
    }

    /**
     * @return DatabaseServer
     */
    public function getDatabaseServer()
    {
        return $this->conf->getDatabaseServer();
    }

    private function checkPreconditions()
    {
        if (!$this->commandExists('mysqldump')) {
            throw new RuntimeException(
                "Command \"mysqldump\" not found"
            );
        }

        if (!$this->commandExists('gzip')) {
            throw new RuntimeException(
                "Command \"gzip\" not found"
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

    /**
     * @return int
     */
    function getDatabasePort()
    {
        return $this->conf->getPort();
    }

    /**
     * @return string
     */
    function getDatabaseUser()
    {
        return $this->conf->getUser();
    }

    /**
     * @return string
     */
    function getDatabaseHost()
    {
        return $this->conf->getHost();
    }
}
