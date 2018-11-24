<?php

namespace Datashot;

use Closure;
use PDO;
use RuntimeException;

class DatabaseSnapper
{
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

    /** @var array */
    private $args;

    /** @var PDO */
    private $pdo;

    /** @var string */
    private $snapfile;

    /** @var callable */
    private $defaultWhereBuilder;

    /** @var callable[] */
    private $whereBuilders = [];

    /** @var string */
    private $connectionFile;

    /** @var array */
    private $listeners = [];

    public function __construct(array $args)
    {
        $this->checkPreconditions();

        $default = [
            'data_only' => false,
            'no_data' => false
        ];

        $this->args = (object) array_merge($default, $args);

        $this->defaultWhereBuilder = function () {
            return 'true';
        };
    }

    public function snap()
    {
        $this->snapping();

        $this->connect();

        $this->setupConnectionFile();

        $this->touch();

        if ($this->dumpAll()) {
            $this->dumpSchema();
        }

        $start = microtime(true);

        if ($this->dumpData()) {
            $this->dumpTables();
        }

        if ($this->dumpAll()) {
            $this->dumpActions();
        }

        $end = microtime(true);

        $this->snapped($start, $end);
    }

    public function where(...$param)
    {
        if (count($param) == 1) {

            if (is_array($param[0])) {
                foreach ($param[0] as $table => $where) {
                    $this->where($table, $where);
                }
                return;
            }

            $this->defaultWhereBuilder = $this->wrap($param[0]);
        } else {
            $table = $param[0];
            $where = $param[1];
            $this->whereBuilders[$table] = $this->wrap($where);
        }

        return $this;
    }

    private function connect()
    {
        $dsn = "mysql:host={$this->args->database_host};" .
            "port={$this->args->database_port};" .
            "dbname={$this->args->database_name}";

        $this->pdo = new PDO($dsn, $this->args->database_user, $this->args->database_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_TIMEOUT => static::DATABASE_TIMEOUT
        ]);
    }

    private function dumpSchema()
    {
        $this->notify(static::DUMPING_SCHEMA);
        $this->appendMsg("Restaurando schema...");
        $this->appendOutput("
            /usr/bin/mysqldump --defaults-file={$this->connectionFile} \
                --no-data \
                --skip-triggers \
                --single-transaction \
                --lock-tables=false \
                {$this->args->database_name}
        ");
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
                {$this->args->database_name}
        ");
    }

    private function eachTable($closure)
    {
        $results = $this->pdo->query("
            SELECT table_name
            FROM information_schema.tables
            where table_schema='{$this->args->database_name}'
        ");

        foreach ($results as $res) {

            $table = $res->table_name;

            call_user_func($closure, $table);
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
                {$this->args->database_name} {$table}
        ");

        $end = microtime(true);

        $this->notify(static::TABLE_DUMPED, [
            'table' => $table,
            'execution_time' => ($end - $start)
        ]);
    }

    private function touch()
    {
        $outputDir= dirname($this->args->output_file);

        $this->mkdir($outputDir);

        $this->snapfile = $this->args->output_file;

        $this->notify(static::CREATING_SNAP_FILE, [
            'snap_file' => $this->snapfile
        ]);

        if (file_exists($this->snapfile) && !unlink($this->snapfile)) {
            throw new RuntimeException(
                "Não foi possível limpar o arquivo de snap \"{$this->snapfile}\""
            );
        }

        if (!touch($this->snapfile)) {
            throw new RuntimeException(
                "Não foi possível escrever o arquivo \"{$this->snapfile}\""
            );
        }
    }

    private function mkdir($dir)
    {
        if (is_dir($dir)) {
            // Diretório já existe, n há a necessidade de recria-lo
            return;
        }

        if (!@mkdir($dir, 0755, true)) {
            throw new RuntimeException(
                "Não foi possível criar o diretorio de \"{$dir}\""
            );
        }
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

        return call_user_func($builder, $table);
    }

    private function lookupBuilder($table)
    {
        if (isset($this->whereBuilders[$table])) {
            return $this->whereBuilders[$table];
        } else {
            return $this->defaultWhereBuilder;
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
            "host={$this->args->database_host}\n" .
            "port={$this->args->database_port}\n" .
            "user={$this->args->database_user}\n" .
            "password={$this->args->database_password}\n"
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

    public function on($event, $callback)
    {
        $this->addListener($event, $callback);
    }

    private function addListener($event, Closure $callback)
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $callback;
    }

    private function notify($event, $data = [])
    {
        $listeners = isset($this->listeners[$event])
            ? $this->listeners[$event] : [];

        foreach ($listeners as $listener) {
            call_user_func($listener, $data);
        }
    }

    private function strip($whereClause)
    {
        $whereClause = str_replace("\n", "", $whereClause);
        return preg_replace("/[ ]{2,}/", ' ', $whereClause);
    }

    private function dumpAll()
    {
        return ! $this->args->data_only;
    }

    private function dumpData()
    {
        return ! $this->args->no_data;
    }

    private function dumpTables()
    {
        $this->eachTable(function ($table) use (&$i) {

            $where = $this->buildWhereClause($table);

            $this->dump($table, $where);
        });
    }

    private function snapping()
    {
        $this->notify(static::SNAPPING, $this->args);
    }

    private function snapped($start, $end)
    {
        $this->notify(static::SNAPED, [
            'execution_time' => ($end - $start)
        ]);
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
}
