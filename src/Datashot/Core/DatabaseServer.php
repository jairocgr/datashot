<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;

class DatabaseServer
{
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
}