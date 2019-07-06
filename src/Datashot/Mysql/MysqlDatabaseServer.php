<?php

namespace Datashot\Mysql;

use Datashot\Core\Database;
use Datashot\Core\DatabaseServer;
use Datashot\Core\DatabaseSnapper;
use Datashot\Core\EventBus;
use Datashot\Core\Shell;
use Datashot\Core\Snap;
use Datashot\Core\SnapperConfiguration;
use Datashot\Lang\Asserter;
use Datashot\Lang\DataBag;
use InvalidArgumentException;
use PDO;
use RuntimeException;

class MysqlDatabaseServer implements DatabaseServer
{
    const DATABASE_TIMEOUT = 60 * 60 * 16;

    /**
     * Mysql driver id handle
     */
    const DRIVER_HANDLE = 'mysql';

    /**
     * @var string
     */
    private $name;

    /**
     * @var PDO
     */
    private $conn;

    /**
     * @var EventBus
     */
    private $bus;

    /**
     * @var Shell
     */
    private $shell;

    /**
     * @var DataBag
     */
    private $data;

    /**
     * @var string
     */
    private $socket;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $user;

    /**
     * @var string
     */
    private $password;

    /**
     * @var boolean
     */
    private $production;

    /**
     * @var string
     */
    private $connectionFile;

    /**
     * @var Database[]
     */
    private $databases;

    public function __construct($name, DataBag $data, $bus, $shell)
    {
        $this->name = $this->filterName($name);
        $this->bus = $bus;
        $this->data = $data;
        $this->shell = $shell;

        $this->socket = $this->extractSocket($data);
        $this->host = $this->extractHost($data);
        $this->port = $this->extractPort($data);
        $this->user = $this->extractUser($data);
        $this->password = $this->extractPassword($data);
        $this->production = $this->extractProduction($data);


        if (empty($this->socket) && empty($this->host)) {
            throw new InvalidArgumentException(
                "Server \"{$this}\" must have a host or a unix socket!"
            );
        }

        if ($this->viaTcp()) {
            if (empty($this->host)) {
                throw new InvalidArgumentException(
                    "Host for server \"{$this}\" cannot be empty!"
                );
            }

            if (empty($this->port)) {
                throw new InvalidArgumentException(
                    "Port for server \"{$this}\" cannot be empty!"
                );
            }
        }
    }

    /**
     * @return bool
     */
    public function viaTcp()
    {
        return empty($this->socket);
    }

    /**
     * @return string
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    private function extractSocket(DataBag $data)
    {
        return $data->extract('socket', '', function ($value, Asserter $a) {

            if ($a->stringfyable($value) && ("{$value}" === '' || $a->notEmptyString($value))) {
                return strval($value);
            }

            $a->raise("Invalid socket :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    private function extractHost(DataBag $data)
    {
        return $data->extract('host', '', function ($value, Asserter $a) {

            if ($a->stringfyable($value) && ("{$value}" === '' || $a->notEmptyString($value))) {
                return strval($value);
            }

            $a->raise("Invalid host :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    private function extractPort(DataBag $data)
    {
        return $data->extract('port', 3306, function ($value, Asserter $a) {

            if ($a->integerfyable($value)) {
                return intval($value);
            }

            $a->raise("Invalid port :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    private function extractUser(DataBag $data)
    {
        return $data->extract('user', '', function ($value, Asserter $a) {

            if ($a->stringfyable($value) && (empty(strval($value)) || $a->notEmptyString($value))) {
                return strval($value);
            }

            $a->raise("Invalid user :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    private function extractProduction(DataBag $data)
    {
        return $data->extract('production', FALSE, function ($value, Asserter $a) {

            if ($a->booleanable($value)) {
                return boolval($value);
            }

            $a->raise("Invalid production :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    private function extractPassword(DataBag $data)
    {
        return $data->extract('password', '', function ($value, Asserter $a) {

            if ($a->stringfyable($value) && (empty(strval($value)) || $a->notEmptyString($value))) {
                return strval($value);
            }

            $a->raise("Invalid password :value on :server server!", [
                'value' => $value,
                'server' => $this
            ]);
        });
    }

    /**
     * @return PDO
     */
    public function openConnection()
    {
        if ($this->viaTcp()) {
            $dsn = "mysql:host={$this->host};port={$this->port};";
        } else {
            $dsn = "mysql:unix_socket={$this->socket};";
        }

        return new PDO($dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_TIMEOUT => static::DATABASE_TIMEOUT,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]);
    }

    private function filterName($name)
    {
        if (is_string($name) && preg_match("/^([a-z][\w\d\-\_\.]*)$/i", $name)) {
            return $name;
        }

        else throw new InvalidArgumentException(
            "Invalid server name \"{$name}\"! Only letters, dashes, underscores, dots, and numbers."
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return DatabaseSnapper
     */
    public function getSnapper(SnapperConfiguration $snapper)
    {
        return new MysqlDatabaseSnapper($this, $this->bus, $this->shell, $snapper);
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->getName();
    }

    /**
     * @inheritDoc
     */
    function exec($command)
    {
        return $this->conn()->exec($command);
    }

    /**
     * @inheritDoc
     */
    function run($script)
    {
        return $this->callMysqlClient(NULL, $script);
    }

    /**
     * @inheritDoc
     */
    public function lookupDatabases($patterns)
    {
        $found = [];

        foreach ($this->wrap($patterns) as $pattern) {
            $found = array_merge($found, $this->findDatabases($pattern));
        }

        return $found;
    }

    /**
     * @inheritDoc
     */
    public function replicate(Database $sourceDatabase, DatabaseServer $target, $destinationDatabase = NULL)
    {
        $this->checkIfWeCanWorkWith($target);

        $destinationDatabase = empty($destinationDatabase) ? $sourceDatabase->getName() : $destinationDatabase;

        if ($this->connectionFileNotCreated()) {
            $this->setupConnectionFile();
        }

        $source = $this->getSchemata($sourceDatabase);

        $target->createDatabase($destinationDatabase, new DataBag([
            'charset' => $source->DEFAULT_CHARACTER_SET_NAME,
            'collation' => $source->DEFAULT_COLLATION_NAME
        ]));

        if ($target->connectionFileNotCreated()) {
            $target->setupConnectionFile();
        }

        $args = implode(' ', [
            " --defaults-file={$this->connectionFile}",
            "--single-transaction",
            "--quick",
            "--no-tablespaces",
            "--disable-keys",
            "--no-autocommit",
            "--lock-tables=false",
            "--skip-comments",
            "--routines",
            "--triggers",
            $sourceDatabase
        ]);

        $this->shell->run("
            mysqldump {$args} \
             | sed -E 's/DEFINER=`[^`]+`@`[^`]+`/DEFINER=CURRENT_USER/g' \
             | mysql --defaults-file={$target->connectionFile} --unbuffered {$destinationDatabase}
        ");

    }

    /**
     * @inheritDoc
     */
    public function createDatabase($database, DataBag $args)
    {
        $conn = $this->getConnection();

        if ($this->isDevelopment()) {
            $conn->exec("DROP DATABASE IF EXISTS `{$database}`");
            // Is is not development database server, we can not willy-nilly
            // just drop a database
        }

        $collate = $args->exists('collation') ? "COLLATE {$args->collation}" : NULL;

        $conn->exec("CREATE DATABASE `{$database}` CHARACTER SET {$args->get('charset', 'uf8')} {$collate}");

        return new MysqlDatabase($database, $this);
    }

    /**
     * @return PDO
     */
    private function conn()
    {
        return $this->getConnection();
    }

    /**
     * @return PDO
     */
    private function getConnection()
    {
        if ($this->conn == NULL) {
            $this->conn = $this->openConnection();
        }

        return $this->conn;
    }

    private function invalidDatabasePattern($pattern)
    {
        return (!preg_match("/^[0-9A-Za-z\_\-\.\*]+$/", $pattern));
    }

    /**
     * @inheritDoc
     */
    public function query($sql)
    {
        return $this->conn()->query($sql);
    }

    /**
     * @return boolean
     */
    public function isProduction()
    {
        return $this->production;
    }

    /**
     * @return boolean
     */
    public function isDevelopment()
    {
        return ! $this->isProduction();
    }

    private function genTempFilePath()
    {
        return tempnam(sys_get_temp_dir(), '.my.cnf.');
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

        if ($this->viaTcp()) {
            $connectionParams = "host={$this->host}\n" .
                                "port={$this->port}\n";
        } else {
            $connectionParams = "socket={$this->socket}\n";
        }

        $res = fwrite($temp,
            "[client]\n" .
            $connectionParams .
            "user={$this->user}\n" .
            "password={$this->password}\n"
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

    private function looksLikeAScriptPath($path)
    {
        return $path == 'php://stdin' || is_file($path);
    }

    private function openScriptFile($filepath)
    {
        if ($filepath == "php://stdin") {
            return $this->fopen($filepath, 'r');
        } elseif (file_exists($filepath)) {
            return $this->fopen($filepath, 'r');
        } else {
            throw new InvalidArgumentException(
                "File \"{$filepath}\" does not exists!"
            );
        }
    }

    private function fopen($filepath, $mode)
    {
        if ($handle = fopen($filepath, $mode)) {
            return $handle;
        } else {
            throw new RuntimeException("File \"{$filepath}\" could not be opened!");
        }
    }

    /**
     * @param $database string|null|DatabaseServer
     * @param $input string|resource
     * @return string
     */
    public function callMysqlClient($database, $input, $compressedInput = FALSE)
    {
        if (is_resource($input)) {
            // Input is ready
            $input = $input;
        } elseif ($this->looksLikeAScriptPath($input)) {
            $input = $this->openScriptFile($input);
        } else {
            $input = strval($input);
        }

        if (!$this->commandExists('mysql')) {
            throw new RuntimeException(
                "Command \"mysql\" not found"
            );
        }

        if (!$this->commandExists('cat')) {
            throw new RuntimeException(
                "Command \"cat\" not found"
            );
        }

        if (!$this->commandExists('gunzip')) {
            throw new RuntimeException(
                "Command \"cat\" not found"
            );
        }

        if ($this->connectionFileNotCreated()) {
            $this->setupConnectionFile();
        }

        if ($compressedInput) {
            $cmd = "gunzip | ";
        } else {
            $cmd = "cat -- | ";
        }

        $cmd .= "mysql --defaults-file={$this->connectionFile} -n --table {$database}";

        return $this->shell->run($cmd, $input);
    }

    private function connectionFileExists()
    {
        return file_exists($this->connectionFile);
    }

    private function connectionFileNotCreated()
    {
        return ! $this->connectionFileExists();
    }

    private function commandExists($command)
    {
        $return_var = 1;
        $stdout = [];

        exec(" ( command -v {$command} > /dev/null 2>&1 ) 2>&1 ", $stdout, $return_var);

        return $return_var === 0;
    }

    private function checkIfWeCanWorkWith(DatabaseServer $target)
    {
        if ($target instanceof MysqlDatabaseServer) {} else {
            throw new InvalidArgumentException(
                "\"{$this}\" can not replicate to \"{$target}\" server!"
            );
        }
    }

    private function getSchemata($database)
    {
        return $this->fetch("
            SELECT * FROM information_schema.SCHEMATA S
            WHERE schema_name = '{$database}'
        ");
    }

    private function fetch($query)
    {
        return $this->query($query)->fetch(PDO::FETCH_OBJ);
    }

    /**
     * @param $database string
     * @param Snap $snap
     */
    function restore($database, Snap $snap)
    {
        $this->callMysqlClient($database, $snap->read(), TRUE);
    }

    /**
     * @return Database[]
     */
    private function getDatabases()
    {
        if ($this->databases == NULL) {
            $this->databases = $this->fetchAllDatabases();
        }

        return $this->databases;
    }

    /**
     * @return Database[]
     */
    private function fetchAllDatabases()
    {
        $databases = [];

        foreach ($this->query("SHOW DATABASES") as $row) {
            $databases[$row->Database] = new MysqlDatabase($row->Database, $this);
        }

        return $databases;
    }

    /**
     * @param $pattern string
     * @return Database[]
     */
    private function findDatabases($pattern)
    {
        if ($this->invalidDatabasePattern($pattern)) {
            throw new InvalidArgumentException(
                "Invalid database identifier \"{$pattern}\"!"
            );
        }

        $matches = [];

        foreach ($this->getDatabases() as $database)
        {
            if ($database->matches($pattern)) {
                $matches[] = $database;
            }
        }

        if (empty($matches)) {
            throw new RuntimeException(
                "Database \"{$pattern}\" not found!"
            );
        }

        return $matches;
    }

    /**
     * @return string[]
     */
    private function wrap($patterns)
    {
        return is_array($patterns) ? $patterns : [ $patterns ];
    }
}
