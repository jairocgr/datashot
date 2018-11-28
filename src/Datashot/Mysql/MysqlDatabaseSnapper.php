<?php

namespace Datashot\Mysql;

use Datashot\Lang\Observable;
use PDO;
use RuntimeException;

class MysqlDatabaseSnapper
{
    use Observable;

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

    private static $EVENTS = [
        self::SNAPED,
    ];

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

    /** @var PDO */
    private $pdo;

    /** @var string */
    private $snapfile;

    /** @var string */
    private $connectionFile;

    public function __construct($config = [])
    {
        $this->conf = new MysqlDumperConfig($config);

        $this->processEventHooks($config);
    }

    public function config(array $config)
    {
        $this->conf->append($config);
        $this->processEventHooks($config);
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

    public function snap()
    {
        $this->snapping();

        $this->connect();

        $this->touchOutput();

        $this->flushFileHeader();

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
        $dsn = "mysql:host={$this->conf->host};" .
               "port={$this->conf->port};" .
               "dbname={$this->conf->database}";

        $this->pdo = new PDO($dsn, $this->conf->username, $this->conf->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_TIMEOUT => static::DATABASE_TIMEOUT,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
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
        $this->notify(static::SNAPED, $this, [
            'execution_time' => ($end - $start)
        ]);
    }

    public function append($command)
    {
        $this->output->writeln($command);
    }

    private function dumpTableDdl($table)
    {
        $ddl = $this->getCreateTableDdl($table);

        $this->output->comment("DDL for \"{$table}\" table ");
        $this->output->message("Creating table {$table}...");
        $this->output->command($ddl);
        $this->output->newLine(2);
    }

    private function dumpViewDdl($table)
    {
        $ddl = $this->getCreateViewDdl($table);

        $this->output->comment("DDL for \"{$table}\" view");
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

        $stmt = $this->pdo->query("
          SELECT {$columns} FROM `{$table}` WHERE {$where}
        ");

        $this->output->comment("Dumping data for table \"{$table}\"");
        $this->output->comment(" WHERE {$this->cutoff($where, 68)}");
        $this->output->message("Restoring {$table}...");

        // Optmization for faster bulky INSERT
        $this->output->command("LOCK TABLES `{$table}` WRITE");
        $this->output->command("ALTER TABLE `{$table}` DISABLE KEYS");
        $this->output->command("SET autocommit = 0");

        $first = TRUE;

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
        $this->eachTriggers(function ($trigger) {
            $this->dumpTrigger($trigger);
        });
    }

    private function eachTriggers($closure)
    {
        $results = $this->pdo->query("
            SHOW TRIGGERS FROM `{$this->conf->database}`
        ");

        foreach ($results as $res) {

            $trigger = $res->Trigger;

            call_user_func($closure, $trigger);
        }
    }

    private function dumpTrigger($trigger)
    {
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
        $this->eachProcedures(function ($procedure) {
            $this->dumpProcedure($procedure);
        });
    }

    private function eachProcedures($closure)
    {
        $results = $this->pdo->query("
            SELECT SPECIFIC_NAME AS procedure_name
            FROM INFORMATION_SCHEMA.ROUTINES
            WHERE ROUTINE_TYPE='PROCEDURE' AND ROUTINE_SCHEMA='{$this->conf->database}'
        ");

        foreach ($results as $res) {

            $procedure = $res->procedure_name;

            call_user_func($closure, $procedure);
        }
    }

    private function dumpProcedure($procedure)
    {
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
        $this->eachFunctions(function ($function) {
            $this->dumpFunction($function);
        });
    }

    private function eachFunctions($closure)
    {
        $results = $this->pdo->query("
            SELECT SPECIFIC_NAME AS function_name
            FROM INFORMATION_SCHEMA.ROUTINES
            WHERE ROUTINE_TYPE='FUNCTION' AND ROUTINE_SCHEMA='{$this->conf->database}'
        ");

        foreach ($results as $res) {

            $function = $res->function_name;

            call_user_func($closure, $function);
        }
    }

    private function dumpFunction($function)
    {
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
        $this->output->comment("Database dump taked via datashot v1.0.0");
        $this->output->comment("  https://github.com/jairocgr/datashot");
        $this->output->comment("");
        $this->output->comment("{$this->conf->database} at {$this->conf->host} server ");
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

    private function processEventHooks($config)
    {
        foreach (static::$EVENTS as $event) {
            // Check if the config array has a event-based hook
            if (isset($config[$event]) && is_callable($config[$event])) {
                $this->on($event, $config[$event]);
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
}
