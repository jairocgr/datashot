<?php

namespace Datashot\Mysql;

use Datashot\Core\Database;
use Datashot\Core\DatabaseServer;
use Datashot\Core\DatabaseSnapper;
use Datashot\Core\EventBus;
use Datashot\Core\Shell;
use Datashot\Core\SnapLocation;
use Datashot\Core\SnapperConfiguration;
use Datashot\Datashot;
use Datashot\IO\GzipFileWriter;
use Datashot\Lang\DataBag;
use PDO;
use RuntimeException;


class MysqlDatabaseSnapper implements DatabaseSnapper
{
    const DATABASE_TIMEOUT = 60 * 60 * 16;

    /** @var SnapperConfiguration */
    private $conf;

    /** @var EventBus */
    private $bus;

    /** @var MysqlDumpFileWriter */
    private $output;

    /** @var PDO */
    private $pdo;

    /** @var string */
    private $snapfile;

    /** @var string */
    private $connectionFile;

    /** @var Shell */
    private $shell;

    /** @var MysqlDatabaseServer */
    private $server;

    /** @var MysqlDatabase */
    private $database;

    /** @var SnapLocation */
    private $destination;

    public function __construct(MysqlDatabaseServer $server, EventBus $bus, Shell $shell, SnapperConfiguration $conf)
    {
        $this->server = $server;
        $this->bus = $bus;
        $this->conf = $conf;

        $this->shell = $shell;

        $this->checkPreconditions();
    }

    /**
     * @inheritDoc
     */
    public function snap(Database $database, SnapLocation $destination)
    {
        $this->database = $database;
        $this->destination = $destination;

        $this->snapping();

        $this->connect();

        $this->setupConnectionFile();

        $this->touchOutput();

        $this->flushFileHeader();

        $start = microtime(true);

        $this->publish(DatabaseSnapper::START_DUMPING);

        $this->beforeHook();

        if ($this->dumpAll()) {
            $this->dumpSchema();
        }

        if ($this->dumpData()) {
            $this->dumpTables();
        }

        if ($this->dumpAll()) {
            $this->dumpActions();
        }

        $this->endStandardServerSettings();

        $this->afterHook();

        $this->sendDumpToLocation($destination);

        $this->publish(DatabaseSnapper::END_DUMPING);

        $end = microtime(true);

        $this->snapped($start, $end);
    }

    private function connect()
    {
        if ($this->server->viaTcp()) {
            $dsn = "mysql:host={$this->server->getHost()};" .
                   "port={$this->server->getPort()};" .
                   "dbname={$this->database}";
        } else {
            $dsn = "mysql:unix_socket={$this->server->getSocket()};" .
                   "dbname={$this->database}";
        }

        $this->pdo = new PDO($dsn, $this->server->getUser(), $this->server->getPassword(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_TIMEOUT => static::DATABASE_TIMEOUT,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]);
    }

    private function buildWhereClause($table)
    {
        $wheres = $this->conf->get('wheres', []);

        if (isset($wheres[$table])) {
            $where = $this->stringfy($wheres[$table]);
        } elseif ($this->conf->hasParam('where')) {
            $where = $this->stringfy($this->conf->get('where'));
        } else {
            return "TRUE";
        }

        $where = $this->strip($where);

        return $this->resolveBoundedParams($where);
    }

    private function stringfy($value)
    {
        if (is_callable($value)) {
            $value = call_user_func($value, $this);
        } elseif ($value === TRUE) {
            $value = 'TRUE';
        } elseif ($value === FALSE) {
            $value = 'FALSE';
        } else {
            $value = strval($value);
        }

        return $this->resolveBoundedParams($value);
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

    private function eachTable($closure)
    {
        $results = $this->pdo->query("
            SELECT table_name
            FROM information_schema.tables
            where table_schema='{$this->database}' AND
                  table_type = 'BASE TABLE'
        ");

        foreach ($results as $res) {

            $table = $res->table_name;

            call_user_func($closure, $table);
        }
    }

    private function snapping()
    {
        $this->publish(DatabaseSnapper::SNAPPING);
    }

    private function snapped($start, $end)
    {
        $this->publish(DatabaseSnapper::SNAPPED, [
            'time' => ($end - $start)
        ]);
    }

    public function append($command)
    {
        if (is_callable($command)) {
            $command = call_user_func($command, $this);

            if (!empty($command)) {
                $this->append($command);
            }
        }

        $command = strval($command);
        $command = $this->resolveBoundedParams($command);

        $this->output->writeln($command);
    }

    private function first($query)
    {
        $stmt = $this->pdo->query($query);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function touchOutput()
    {
        $this->publish(DatabaseSnapper::CREATING_SNAP_FILE);

        $this->snapfile = $this->getTempFile();

        $writer = new GzipFileWriter($this->snapfile);

        $this->output = new MysqlDumpFileWriter($writer);

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

            $row = call_user_func($transformer, $row, $this);
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

    private function flushFileHeader()
    {
        $this->output->comment("charset {$this->database->getCharset()} offset {$this->database->getCollation()}");
        $this->output->comment("");
        $this->output->comment("Database dump taked via ". Datashot::getPackageName() ." v" . Datashot::getVersion());
        $this->output->comment("  " . Datashot::getPackageUrl());
        $this->output->comment("");
        $this->output->comment("{$this->database} from {$this->server} server ");
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

        $this->output->command("SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT");
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
        $this->output->command("SET AUTOCOMMIT=@OLD_AUTOCOMMIT");
        $this->output->newLine(2);
    }

    private function dumpActions()
    {
        $this->publish(DatabaseSnapper::DUMPING_ACTIONS);
        $this->output->comment("Restoring actions");

        // Close and flush the current writings for mysql client
        // shell appending
        $this->output->close();

        $this->appendOutput("
            mysqldump --defaults-file={$this->connectionFile} \
                --set-gtid-purged=OFF \
                --column-statistics=0 \
                --no-create-info \
                --no-data \
                --routines \
                --triggers \
                --single-transaction \
                --lock-tables=false \
                --quick \
                {$this->database}
        ");
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
            'via_php' => TRUE,
            'rows' => $stmt->rowCount(),
            'rows_transformed' => $this->conf->hasRowTransformer($table)
        ]);
    }

    private function dumpTableDataViaMysqlClient($table)
    {
        $where = $this->buildWhereClause($table);

        $this->publish(DatabaseSnapper::DUMPING_TABLE_DATA, [
            'table' => $table,
            'where' => $where
        ]);

        $this->output->comment("Dumping data for table \"{$table}\"");
        $this->output->comment(" WHERE {$this->cutoff($where, 68)}");
        $this->output->message("Restoring {$table}...");

        $start = microtime(true);

        // Close and flush the current writings for mysql client
        // shell appending
        $this->output->close();

        $this->appendOutput("
            mysqldump --defaults-file={$this->connectionFile} \
                --set-gtid-purged=OFF \
                --column-statistics=0 \
                --no-create-info \
                --no-tablespaces \
                --skip-triggers \
                --disable-keys \
                --no-autocommit \
                --single-transaction \
                --lock-tables=false \
                --skip-comments \
                --quick \
                --where=\"{$where}\" \
                {$this->database} {$table}
        ");

        $end = microtime(true);

        $this->publish(DatabaseSnapper::TABLE_DUMPED, [
            'time' => ($end - $start)
        ]);
    }

    private function dumpSchema()
    {
        // Close and flush the current writings for mysql client
        // shell appending
        $this->output->close();

        $this->appendOutput("
            mysqldump --defaults-file={$this->connectionFile} \
                --set-gtid-purged=OFF \
                --column-statistics=0 \
                --no-data \
                --skip-triggers \
                --disable-keys \
                --no-autocommit \
                --single-transaction \
                --lock-tables=false \
                --quick \
                {$this->database}
        ");
    }

    private function strip($whereClause)
    {
        $whereClause = str_replace("\n", "", $whereClause);
        $whereClause = trim($whereClause);
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
        $this->shell->run($command);
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
                "Could not create the connection file " .
                "\"{$this->connectionFile}\"!"
            );
        }

        if ($this->server->viaTcp()) {
            $connectionParams = "host={$this->server->getHost()}\n" .
                "port={$this->server->getPort()}\n";
        } else {
            $connectionParams = "socket={$this->server->getSocket()}\n";
        }

        $res = fwrite($temp,
            "[client]\n" .
            $connectionParams .
            "user={$this->server->getUser()}\n" .
            "password={$this->server->getPassword()}\n"
        );

        if ($res === FALSE) {
            throw new RuntimeException(
                "The connection file \"{$this->connectionFile}\" " .
                "could not be written!"
            );
        }

        if (fflush($temp) === FALSE) {
            throw new RuntimeException(
                "The connection file \"{$this->connectionFile}\" " .
                "could not be written!"
            );
        }

        if (fclose($temp) === FALSE) {
            throw new RuntimeException(
                "The connection file \"{$this->connectionFile}\" " .
                "could not be closed!"
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
    public function getSnappedDatabase()
    {
        return $this->database;
    }

    /**
     * @return DatabaseServer
     */
    public function getSnappedServer()
    {
        return $this->server;
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

        if (!$this->commandExists('cat')) {
            throw new RuntimeException(
                "Command \"cat\" not found"
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

    private function resolveBoundedParams($string)
    {
        if ($this->hasBoundedParams($string)) {

            $params = $this->extractBoundedParams($string);

            foreach ($params as $param) {

                $value = $this->resolveParam($param);

                $string = str_replace("{{$param}}", $value, $string);
            }
        }

        return $string;
    }

    private function resolveParam($param)
    {
        $value = $this->conf->getr($param);

        if (is_callable($value)) {
            $value = call_user_func($value, $this);
        }

        return $value;
    }

    /**
     * @return mixed
     */
    function get($key, $defaultValue = NULL)
    {
        // TODO: Resolver bound parameters and closure call
        return $this->conf->get($key, $defaultValue);
    }

    /**
     * @retun PDOStatement
     */
    function query($sql, $args = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt;
    }

    /**
     * @retun int
     */
    public function execute($sql, $args = [])
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($args);
    }

    /**
     * @return PDO
     */
    public function getConnection() {

        if ($this->pdo == NULL) {
            throw new RuntimeException(
                "Connection not opened yet!"
            );
        }

        return $this->pdo;
    }

    private function hasBoundedParams($string)
    {
        return preg_match("/\{[A-Za-z0-9\-\.\_]+\}/", $string);
    }

    private function extractBoundedParams($string)
    {
        $matches = [];
        $params = [];

        preg_match_all("/\{[A-Za-z0-9\-\.\_]+\}/", $string, $matches);

        foreach ($matches[0] as $match) {
            $match = str_replace('{', '', $match);
            $match = str_replace('}', '', $match);

            $params[] = $match;
        }

        return $params;
    }

    private function beforeHook()
    {
        if ($this->conf->hasParam('before')) {

            $before = $this->conf->get('before');

            $this->append($before);
        }
    }

    private function afterHook()
    {
        if ($this->conf->hasParam('after')) {
            $after = $this->conf->get('after');

            $this->append($after);
        }
    }

    public function puts($string)
    {
        $this->bus->publish('output', $string);
    }

    function set($key, $value)
    {
        $this->conf->set($key, $value);
    }

    /**
     * @return bool
     */
    function has($key)
    {
        return $this->conf->hasParam($key);
    }

    private function getTempFile()
    {
        $path = tempnam(sys_get_temp_dir(), '.snap');

        register_shutdown_function(function () use ($path) {
            @unlink($path);
        });

        return $path;
    }

    private function sendDumpToLocation()
    {
        $this->output->close();

        if ($this->destination->isDirectory()) {
            $out = $this->destination->to($this->database);
            $out->aspirate($this->snapfile);
        } else {
            $this->destination->aspirate($this->snapfile);
        }
    }
}
