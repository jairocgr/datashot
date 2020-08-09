<?php

namespace Datashot\Mysql\Cli;

use Datashot\Core\Shell;
use Datashot\Lang\DataBag;
use Datashot\Lang\TempFile;
use Datashot\Mysql\MysqlDatabaseServer;

class MysqldumpCli
{
    /**
     * @var MysqlDatabaseServer
     */
    private $server;

    /**
     * @var TempFile
     */
    private $connectionFile;

    /**
     * @var Shell
     */
    private $shell;

    /**
     * MysqlCliClient constructor.
     *
     * @param MysqlDatabaseServer $server
     * @param Shell $shell
     */
    public function __construct($server, $shell)
    {
        $this->server = $server;
        $this->shell = $shell;
    }

    public function dump($database, $table = NULL, $gziped = TRUE)
    {
        if ($gziped) {
            $cmd = "gunzip > \"{$script}\" | ";
        } else {
            $cmd = "cat \"{$script}\" | ";
        }

        $cmd .= "mysql --defaults-file={$this->getConnectionFile()} {$database}";

        $this->shell->run($cmd);
    }

    private function getConnectionFile()
    {
        if ($this->connectionFile == NULL) {
            $this->connectionFile = $this->buildConnectionFile();
        }

        return $this->connectionFile;
    }

    private function buildConnectionFile()
    {
        $tmp = new TempFile();

        $tmp->writeln("[client]");

        if ($this->server->viaTcp()) {
            $tmp->writeln("host={$this->server->getHost()}");
            $tmp->writeln("port={$this->server->getPort()}");
        } else {
            $tmp->writeln("socket={$this->server->getSocket()}");
        }

        $tmp->writeln("user={$this->server->getUser()}");
        $tmp->writeln("password={$this->server->getPassword()}");


        // Ouput table mode (--table)
        $tmp->writeln("table");

        // Flush the buffer after each query (--unbuffered)
        $tmp->writeln("unbuffered");


        return $tmp;
    }
}
