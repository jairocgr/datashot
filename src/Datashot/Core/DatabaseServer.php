<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;
use InvalidArgumentException;

class DatabaseServer
{
    /**
     * Mysql driver id handle
     */
    const MYSQL = 'mysql';

    private static $SUPPORTED_DRIVERS = [ self::MYSQL ];

    /**
     * @var string
     */
    private $name;

    /** @var string */
    private $driver;

    /** @var string */
    private $socket;

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $userName;

    /** @var string */
    private $password;

    public function __construct($name, DataBag $data)
    {
        $this->name = $this->filterName($name);
        $this->extract($data);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDriver()
    {
        return $this->driver;
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
        return $this->userName;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return bool
     */
    public function viaTcp()
    {
        return empty($this->socket);
    }

    private function extract(DataBag $data)
    {
        if ($data->notExists('host') && $data->notExists('socket')) {
            throw new InvalidArgumentException(
                "Database \"{$this->name}\" must have a host or a unix socket!"
            );
        }

        $this->driver = $this->extractDriver($data);
        $this->socket = $this->extractSocket($data);
        $this->host = $this->extractHost($data);
        $this->port = $this->extractPort($data);
        $this->userName = $this->extractUserName($data);
        $this->password = $this->extractPassword($data);

        if ($this->viaTcp()) {
            if (empty($this->host)) {
                throw new InvalidArgumentException(
                    "Database host for database \"{$this->name}\" cannot be empty!"
                );
            }

            if (empty($this->port)) {
                throw new InvalidArgumentException(
                    "Database port for database \"{$this->name}\" cannot be empty!"
                );
            }
        }
    }

    private function filterName($name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException("Database server name cannot be empty!");
        }

        if (!is_string($name)) {
            throw new InvalidArgumentException("Database server name \"{$name}\" must be a valid string!");
        }

        return $name;
    }

    private function extractDriver(DataBag $data)
    {
        $value = $data->get("driver");

        if (empty($value)) {
            throw new InvalidArgumentException(
                "Database driver for database \"{$this->name}\" cannot be empty!"
            );
        }

        if (!is_string($value) || !in_array($value, static::$SUPPORTED_DRIVERS)) {
            throw new InvalidArgumentException(
                "Invalid driver \"{$value}\" for \"{$this->name}\" database!"
            );
        }

        return $value;
    }

    private function extractSocket(DataBag $data)
    {
        $value = $data->get("socket");

        if (!empty($value)) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(
                    "Invalid socket \"{$value}\" for \"{$this->name}\" database!"
                );
            }
        }

        return $value;
    }

    private function extractHost(DataBag $data)
    {
        $value = $data->get("host");

        if (!empty($value)) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(
                    "Invalid host \"{$value}\" for \"{$this->name}\" database!"
                );
            }
        }

        return $value;
    }

    private function extractPort(DataBag $data)
    {
        $value = $data->get("port");

        if (!empty($value)) {
            if (is_string($value) && !preg_match("/^[0-9]+$/", $value)) {
                throw new InvalidArgumentException(
                    "Invalid port \"{$value}\" for \"{$this->name}\" database!"
                );
            }

            $validType = is_string($value) || is_integer($value);

            if (!$validType) {
                throw new InvalidArgumentException(
                    "Invalid port \"{$value}\" for \"{$this->name}\" database!"
                );
            }

            $value = intval($value);

            if ($value < 1 || $value > 65535) {
                throw new InvalidArgumentException(
                    "Invalid tcp port \"{$value}\" for \"{$this->name}\" database!"
                );
            }

            return $value;
        } else {
            return NULL;
        }
    }

    private function extractUserName(DataBag $data)
    {
        $value = $data->get("username");

        if (!empty($value)) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(
                    "Invalid username \"{$value}\" for \"{$this->name}\" database!"
                );
            }
        }

        return $value;
    }

    private function extractPassword(DataBag $data)
    {
        $value = $data->get("password");

        if (!empty($value)) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(
                    "Invalid username \"{$value}\" for \"{$this->name}\" database!"
                );
            }
        }

        return $value;
    }

    public function __toString()
    {
        return $this->getName();
    }
}