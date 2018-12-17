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

    public function getSocket()
    {
        return $this->data->get('socket');
    }

    public function viaTcp()
    {
        return $this->data->notExists('socket') ||
               empty($this->data->get('socket'));
    }

    private function validate()
    {
        if ($this->data->notExists('host') && $this->data->notExists('socket')) {
            throw new RuntimeException(
                "{$this->name} database must have a host or a unix socket!"
            );
        }

        if ($this->viaTcp()) {
            $this->data->checkIfNotEmptyString('host', ":key can not be empty {$this->name} database");
            $this->data->regex('port', '/[0-9]+/', "Invalid :key :value for {$this->name} database");
            $this->data->between('port', 1, 65535, "Invalid :key :value for {$this->name} database");
        } else {
            $this->data->checkIfString('socket', "Invalid :key :value for {$this->name} database");
        }

        $this->data->required('username', "Param :key not found at {$this->name} database");
        $this->data->required('driver', "Param :key not found at {$this->name} database");

        $this->data->regex('username', "/.{2,255}/", "Invalid :key :value for {$this->name} database");
        $this->data->oneOf('driver', DatabaseServer::$SUPPORTED_DRIVERS, "Invalid :key :value for {$this->name} database");
    }
}