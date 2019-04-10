<?php

namespace Datashot\Mysql;

use Datashot\Core\DatabaseServer;
use Datashot\Core\EventBus;
use Datashot\Core\RestoringSettings;
use Datashot\Core\Shell;
use Datashot\Core\SnapperConfiguration;
use Datashot\Core\SnapRestorer;
use Datashot\Lang\DataBag;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

class MysqlSnapRestorer implements SnapRestorer
{
    /**
     * @var EventBus
     */
    private $bus;

    /**
     * @var RestoringSettings
     */
    private $config;

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var string
     */
    private $connectionFile;

    /**
     * @var Shell
     */
    private $shell;

    public function __construct(EventBus $bus, Shell $shell, RestoringSettings $config)
    {
        $this->bus = $bus;
        $this->config = $config;

        $this->shell = $shell;

        $this->checkPreConditions();
    }

    public function restore()
    {
        $this->publish(SnapRestorer::RESTORING);

        $start = microtime(true);

        $this->connect();

        $this->dropAndCreateTargetDatabase();

        $this->reconnect();

        $this->beforeHook();

        $this->setupConnectionFile();

        $cmd = $this->sourceIsCompressed() ? "gunzip <" : "cat";

        $this->exec("
            {$cmd} {$this->getSourceFilePath()} |
            mysql --defaults-file={$this->connectionFile} \
                  {$this->getTargetDatabaseName()}
        ");

        $this->afterHook();

        $end = microtime(true);

        $this->publish(SnapRestorer::RESTORED, [
            'time' => ($end - $start)
        ]);
    }

    private function publish($event, array $data = [])
    {
        $this->bus->publish($event, $this, new DataBag($data));
    }

    /**
     * @return DatabaseServer
     */
    public function getTargetDatabase()
    {
        return $this->config->getTargetDatabase();
    }

    /**
     * @return SnapperConfiguration
     */
    public function getSourceSnapper()
    {
        return $this->config->getSourceSnapper();
    }

    /**
     * @return string
     */
    public function getSourceFileName()
    {
        return $this->getSourceSnapper()->getOutputFileName();
    }

    private function connect()
    {
        $target = $this->getTargetDatabase();

        if ($target->viaTcp()) {
            $dsn = "mysql:host={$target->getHost()};" .
                   "port={$target->getPort()};";
        } else {
            $dsn = "mysql:unix_socket={$target->getSocket()};";
        }

        $this->pdo = new PDO($dsn, $target->getUser(), $target->getPassword(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]);
    }

    private function reconnect()
    {
        $target = $this->getTargetDatabase();

        if ($target->viaTcp()) {
            $dsn = "mysql:host={$target->getHost()};" .
                   "port={$target->getPort()};" .
                   "dbname={$this->getTargetDatabaseName()}";
        } else {
            $dsn = "mysql:unix_socket={$target->getSocket()};" .
                   "dbname={$this->getTargetDatabaseName()}";
        }

        $this->pdo = new PDO($dsn, $target->getUser(), $target->getPassword(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]);
    }

    private function dropAndCreateTargetDatabase()
    {
        $target = $this->getTargetDatabaseName();
        $charset = $this->getDatabaseCharset();
        $collation = $this->getDatabaseCollation();

        $this->publish(SnapRestorer::CREATING_DATABASE);

        $this->pdo->exec("DROP DATABASE IF EXISTS `{$target}`");
        $this->pdo->exec("CREATE DATABASE `{$target}` CHARSET {$charset} COLLATE {$collation}");
    }

    /**
     * @return string
     */
    function getTargetDatabaseName()
    {
        return $this->config->getTargetDatabaseName();
    }

    /**
     * @return string
     */
    function getDatabaseCharset()
    {
        return $this->config->getDatabaseCharset();
    }

    /**
     * @return string
     */
    function getDatabaseCollation()
    {
        return $this->config->getDatabaseCollation();
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

        $target = $this->getTargetDatabase();

        if ($target->viaTcp()) {
            $connectionParams = "host={$target->getHost()}\n" .
                                "port={$target->getPort()}\n";
        } else {
            $connectionParams = "socket={$target->getSocket()}\n";
        }

        $res = fwrite($temp,
            "[client]\n" .
            $connectionParams .
            "user={$target->getUser()}\n" .
            "password={$target->getPassword()}\n"
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
        return tempnam(sys_get_temp_dir(), '.my.cnf');
    }

    private function exec($command)
    {
        $process = $this->shell->sidekick($command);

        $errmsg = "";

        foreach ($process as $type => $data) {

            if ($type == Process::ERR) {
                $errmsg .= $data;
            }

            $this->publish(SnapRestorer::STDOUT, [
                'type' => $type,
                'data' => $data
            ]);
        }

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                "Troubles executing the command! {$errmsg}"
            );
        }
    }

    private function getSourceFilePath()
    {
        return $this->getSourceSnapper()->getOutputFilePath();
    }

    private function sourceIsCompressed()
    {
        return $this->getSourceSnapper()->compressOutput();
    }

    private function beforeHook()
    {
        if ($this->config->hasBeforeHook()) {
            $hook = $this->config->getBeforeHook();

            if (is_callable($hook)) {
                $ret = call_user_func($hook, $this);

                if (is_string($ret)) {
                    $this->pdo->exec($ret);
                }

                return;
            }

            $this->pdo->exec($hook);
        }
    }

    private function afterHook()
    {
        if ($this->config->hasAfterHook()) {
            $hook = $this->config->getAfterHook();

            if (is_callable($hook)) {
                $ret = call_user_func($hook, $this);

                if (is_string($ret)) {
                    $this->pdo->exec($ret);
                }

                return;
            }

            $this->pdo->exec($hook);
        }
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
        $stmt->execute($args);
        return $stmt->rowCount();
    }

    /**
     * @return PDO
     */
    public function getConnection()
    {
        if ($this->pdo == NULL) {
            throw new RuntimeException(
                "Connection not opened yet!"
            );
        }

        return $this->pdo;
    }

    private function checkPreconditions()
    {
        if (!$this->commandExists('mysql')) {
            throw new RuntimeException(
                "Command \"mysql\" not found"
            );
        }

        if (!$this->commandExists('gunzip')) {
            throw new RuntimeException(
                "Command \"gunzip\" not found"
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

    public function puts($string)
    {
        $this->bus->publish('output', $string);
    }

    function set($key, $value)
    {
        return $this->config->set($key, $value);
    }

    /**
     * @return mixed
     */
    function get($key, $defaultValue = NULL)
    {
        return $this->config->get($key, $defaultValue);
    }
}