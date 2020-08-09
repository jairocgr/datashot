<?php


namespace Datashot\Mysql;

use Datashot\Core\Database;
use Datashot\Core\DatabaseServer;
use Datashot\Core\Snap;
use Datashot\Core\SnapLocation;
use Datashot\Core\SnapperConfiguration;
use Datashot\Datashot;
use PDO;

class MysqlDatabase implements Database
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var MysqlDatabaseServer
     */
    private $server;

    /**
     * @var PDO
     */
    private $conn;

    public function __construct($name, MysqlDatabaseServer $server)
    {
        $this->name = $name;
        $this->server = $server;
    }

    /**
     * @inheritDoc
     */
    function getName()
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    function __toString()
    {
        return $this->getName();
    }

    /**
     * @inheritDoc
     */
    function run($command)
    {
        $this->server->callMysqlClient($this, $command);
    }

    /**
     * @inheritDoc
     */
    function matches($pattern)
    {
        return fnmatch($pattern, $this->name);
    }

    /**
     * @inheritDoc
     */
    function snap(SnapperConfiguration $config, SnapLocation $output)
    {
        return $this->server->getSnapper($config)->snap($this, $output);
    }

    /**
     * @inheritDoc
     */
    function restore(Snap $snap)
    {
        $this->server->restore($this, $snap);
    }

    /**
     * @inheritDoc
     */
    function replicateTo(DatabaseServer $target, $name)
    {
        $intermediate = $this->dumpIt();

        $target->restore($name, $intermediate);

        $intermediate->rm();
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
    function query($sql)
    {
        return $this->conn()->query($sql);
    }

    /**
     * @return PDO
     */
    private function conn()
    {
        if ($this->conn == NULL) {
            $this->conn = $this->buildConnection();
        }

        return $this->conn;
    }

    /**
     * @return PDO
     */
    private function buildConnection()
    {
        $conn = $this->server->openConnection();
        $conn->exec("USE `{$this}`");

        return $conn;
    }

    public function getCharset()
    {
        return $this->conn()->query('SELECT @@character_set_database')->fetchColumn();
    }

    public function getCollation()
    {
        return $this->conn()->query('SELECT @@collation_database')->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function dumpIt()
    {
        return $this->server->dump($this);
    }
}
