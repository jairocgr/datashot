<?php

namespace Datashot\Mysql;

use Datashot\Lang\DataBag;
use InvalidArgumentException;
use PDO;

class MysqlConnectionParams
{
    /** @var MysqlDatabaseServer */
    private $server;

    /** @var string */
    private $driver;

    /** @var string */
    private $socket;

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $user;

    /** @var string */
    private $password;

    public function __construct(MysqlDatabaseServer $server, DataBag $data)
    {
        $this->server = $server;

        if ($data->notExists('host') && $data->notExists('socket')) {
            throw new InvalidArgumentException(
                "Database \"{$this->server}\" must have a host or a unix socket!"
            );
        }

        $this->driver = $this->extractDriver($data);
        $this->socket = $this->extractSocket($data);
        $this->host = $this->extractHost($data);
        $this->port = $this->extractPort($data);
        $this->user = $this->extractUser($data);
        $this->password = $this->extractPassword($data);

        if ($this->viaTcp()) {
            if (empty($this->host)) {
                throw new InvalidArgumentException(
                    "Database host for database \"{$this->server}\" cannot be empty!"
                );
            }

            if (empty($this->port)) {
                throw new InvalidArgumentException(
                    "Database port for database \"{$this->server}\" cannot be empty!"
                );
            }
        }
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
        return $this->user;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    private function extractDriver(DataBag $data)
    {
        $value = $data->get("driver");

        if (empty($value)) {
            throw new InvalidArgumentException(
                "Database driver for database \"{$this->server}\" cannot be empty!"
            );
        }

        if (!is_string($value) || $value != MysqlDatabaseServer::DRIVER_HANDLE) {
            throw new InvalidArgumentException(
                "Invalid driver \"{$value}\" for \"{$this->server}\" server!"
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
                    "Invalid socket \"{$value}\" for \"{$this->server}\" server!"
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
                    "Invalid host \"{$value}\" for \"{$this->server}\" server!"
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
                    "Invalid port \"{$value}\" for \"{$this->server}\" server!"
                );
            }

            $validType = is_string($value) || is_integer($value);

            if (!$validType) {
                throw new InvalidArgumentException(
                    "Invalid port \"{$value}\" for \"{$this->server}\" server!"
                );
            }

            $value = intval($value);

            if ($value < 1 || $value > 65535) {
                throw new InvalidArgumentException(
                    "Invalid tcp port \"{$value}\" for \"{$this->server}\" server!"
                );
            }

            return $value;
        } else {
            return NULL;
        }
    }

    private function extractUser(DataBag $data)
    {
        $value = $data->get("user");

        if (!empty($value)) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(
                    "Invalid user \"{$value}\" for \"{$this->server}\" server!"
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
                    "Invalid user \"{$value}\" for \"{$this->server}\" server!"
                );
            }
        }

        return $value;
    }

    /**
     * @return PDO
     */
    public function openConnection($options = [])
    {
        if ($this->viaTcp()) {
            $dsn = "mysql:host={$this->host};port={$this->port};";
        } else {
            $dsn = "mysql:unix_socket={$this->socket};";
        }

        return new PDO($dsn, $this->user, $this->password, $options);
    }
}