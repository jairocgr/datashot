<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;
use RuntimeException;

class DatabaseServer
{
    private static $SUPPORTED_DRIVERS = [ 'mysql' ];

    /**
     * @var string
     */
    private $name;

    /** @var DataBag */
    private $data;

    public function __construct($name, DataBag $data)
    {
        $this->name = $name;
        $this->data = $data;

        $this->validate();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getHost()
    {
        return $this->data->host;
    }

    public function getPort()
    {
        return $this->data->port;
    }

    public function getUserName()
    {
        return $this->data->username;
    }

    public function getPassword()
    {
        return $this->data->password;
    }

    public function getDriver()
    {
        return $this->data->driver;
    }

    public function getUnixSocket()
    {
        return $this->data->get('unix_socket');
    }

    public function viaTcp()
    {
        return $this->data->notExists('socket') ||
               empty($this->data->get('socket'));
    }

    private function validate()
    {
        if ($this->data->notExists('host') && $this->data->notExists('unix_socket')) {
            throw new RuntimeException(
                "{$this->name} database must have a host or a unix socket!"
            );
        }

        if ($this->data->notExists('unix_socket')) {
            $this->data->regex('host', "/[a-z0-9\.\-\_]{2,255}/", "Invalid host :value for {$this->name} database");
            $this->data->regex('port', '/[0-9]+/', "Invalid port :value for {$this->name} database");
            $this->data->between('port', 1, 65535, "Invalid port number :value for {$this->name} database");
        }

        if ($this->data->notExists('host')) {
            $this->data->regex('unix_socket', '/[a-z0-9\.\-\_\/]{2,255}/', "Invalid socket :value for {$this->name} database");
        }

        $this->data->required([ 'username', 'driver' ], "Param :key not found at {$this->name} database");

        $this->data->regex('username', "/.{2,255}/", "Invalid user :value for {$this->name} database");
        $this->data->oneOf('driver', DatabaseServer::$SUPPORTED_DRIVERS, "Invalid driver :value for {$this->name} database");
    }
}