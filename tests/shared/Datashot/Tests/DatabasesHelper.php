<?php


namespace Datashot\Tests;


use Datashot\Lang\DataBag;
use PDO;
use PDOStatement;

class DatabasesHelper
{
    /**
     * @var DataBag
     */
    private $config;

    public function __construct($config)
    {
        $this->config = new DataBag($config);
    }

    public function queryFirst($server, $database, $sql)
    {
        $res = $this->query($server, $database, $sql);

        return $res->fetchObject();
    }

    /**
     * @return false|PDOStatement
     */
    private function query($server, $database, $sql)
    {
        $conn = $this->connect($server, $database);
        return $conn->query($sql);
    }

    /**
     * @param $server
     * @param $database
     * @return PDO
     */
    private function connect($server, $database)
    {
        $config = $this->config->get("database_servers.{$server}");

        $dsn = "mysql:host={$config->host};port={$config->port};" .
               "dbname={$database}";

        return new PDO($dsn, $config->user, $config->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]);
    }
}