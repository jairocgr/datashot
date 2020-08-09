<?php

namespace Datashot\Mysql;

use Datashot\Core\Database;
use Datashot\Core\DatabaseServer;
use Datashot\Core\DatabaseSnapper;
use Datashot\Core\EventBus;
use Datashot\Core\Shell;
use Datashot\Core\Snap;
use Datashot\Core\SnapperConfiguration;
use Datashot\Datashot;
use Datashot\Lang\Asserter;
use Datashot\Lang\DataBag;
use Datashot\Lang\TempFile;
use Datashot\Mysql\Cli\MysqlCliClient;
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
     * @var string
     */
    private $sslMode;

    /**
     * @var Database[]
     */
    private $databases;

    /**
     * @var Datashot
     */
    private $datashot;

    /**
     * @var MysqlCliClient
     */
    private $mysql;

    public function __construct($datashot, $name, DataBag $data, $bus, $shell)
    {
        $this->datashot = $datashot;
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
        $this->sslMode = $this->extractSslMode($data);


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

        $this->mysql = new MysqlCliClient($this, $this->shell);
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

    private function extractSslMode(DataBag $data)
    {
        return $data->extract('ssl-mode', 'DISABLED', function ($value, Asserter $a) {

            if ($a->stringfyable($value) && (empty(strval($value)) || $a->notEmptyString($value))) {
                return strval($value);
            }

            $a->raise("Invalid ssl-mode :value on :server server!", [
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
     * @inheritDoc
     */
    function canReplicateTo(DatabaseServer $target)
    {
        return $target instanceof MysqlDatabaseServer &&
               $target->isDevelopment();
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
    public function getSnapper(SnapperConfiguration $snapper = null)
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

    public function getSslMode()
    {
        return $this->sslMode;
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
            throw new RuntimeException("Only file path input allowed!");
        } elseif ($input == 'php://stdin') {
            $tmp = new TempFile();
            $tmp->sink($this->fopen($input, 'r'));
            $input = $tmp->getPath();
        } elseif (file_exists($input)) {
            $input = $input;
        } else {
            $input = $this->buildTmpSqlInputFile($input);
        }

        $this->mysql->exec($input, $compressedInput, $database);
    }

    /**
     * @param $database string
     * @param Snap $snap
     */
    function restore($database, Snap $snap)
    {
        if ($this->databaseDontExists($database)) {
            $this->createDatabase($database, new DataBag([
                'charset' => $snap->getCharset()
            ]));
        }

        $this->callMysqlClient($database, $snap->getPhysicalPath(), TRUE);
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


    public function dump(MysqlDatabase $database)
    {
        $snapper = $this->datashot->getDefaultSnapper();

        $time = date("Ymdhis");

        $out = $this->datashot->parse("/tmp/{$database}-{$time}");

        $database->snap($snapper, $out);

        return $out->toSnap();
    }

    private function databaseDontExists($database)
    {
        return ! $this->databaseExists($database);
    }

    private function databaseExists($name)
    {
        foreach ($this->getDatabases() as $database) {
            if ($database->getName() == $name) {
                return TRUE;
            }
        }

        return FALSE;
    }

    private function buildTmpSqlInputFile($input)
    {
        $tmp = new TempFile();
        $tmp->write($input);

        return $tmp;
    }
}
