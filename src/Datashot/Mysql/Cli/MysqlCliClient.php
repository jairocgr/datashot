<?php

namespace Datashot\Mysql\Cli;

use Datashot\Core\Shell;
use Datashot\Lang\TempFile;
use Datashot\Mysql\MysqlDatabaseServer;

class MysqlCliClient
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
     * @var string
     */
    private $version;

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

        $this->checkEnvironment();
    }

    public function exec($script, $gziped = FALSE, $database = NULL)
    {
        if ($gziped) {
            $cmd = "gunzip < \"{$script}\" | ";
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

        // Disable ssl
        $tmp->writeln("ssl-mode={$this->server->getSslMode()}");


        // Ouput table mode (--table)
        $tmp->writeln("table");

        // Flush the buffer after each query (--unbuffered)
        $tmp->writeln("unbuffered");


        return $tmp;
    }

    private function checkEnvironment()
    {
        $this->checkCommand('mysql');
        $this->checkCommand('cat');
        $this->checkCommand('gunzip');

        $this->version = $this->readClientVersion();
    }

    private function checkCommand($cmd)
    {
        if ($this->commandExists($cmd)) {} else {
            throw new RuntimeException(
                "Command \"{$cmd}\" not found on path!"
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

    private function readClientVersion()
    {
        $out = $this->shell->run("mysql --version");
        $matches = [];

        preg_match("/(\d+\.\d+\.+\d+)/", $out, $matches);

        if (isset($matches[1])) {
            return trim($matches[1]);
        } else {
            return "unknown";
        }
    }
}
