<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;
use RuntimeException;

class Configuration
{
    /** @var DataBag */
    private $data;

    /**
     * @var SnapperConfiguration[]
     */
    private $snappers = [];

    /**
     * @var DatabaseServer[]
     */
    private $databaseServers = [];

    public function __construct(array $data)
    {
        $this->parseConfiguration($data);
    }

    private function parseConfiguration(array $data)
    {
        $this->data = new DataBag($data);

        $this->parseDatabaseServers();
        $this->parseSnappers();
    }

    private function parseDatabaseServers()
    {
        $servers = $this->data->getr('database_servers');

        if (empty($servers)) {
            throw new RuntimeException(
                "Database servers can not be empty!"
            );
        }

        foreach ($servers as $server => $config) {
            $this->databaseServers[$server] = new DatabaseServer($server, $config);
        }
    }

    private function parseSnappers()
    {
        $snappers = $this->data->getr("snappers");

        if (empty($snappers)) {
            throw new RuntimeException(
                "Snappers can not be empty!"
            );
        }

        foreach ($snappers as $snapperName => $data) {
            $this->snappers[$snapperName] = $this->parseSnapper($snapperName, $data);
        }
    }

    /**
     * @return DatabaseServer
     */
    public function getDatabase($name)
    {
        if (!isset($this->databaseServers[$name])) {
            throw new RuntimeException("Database server \"{$name}\" not fount!");
        }

        return $this->databaseServers[$name];
    }

    private function parseSnapper($snapperName, DataBag $data)
    {
        if ($data->exists('database_server')) {

            $serverName = $data->get('database_server');

            $data->set('database_server', $this->getDatabase($serverName));
        }

        return new SnapperConfiguration($snapperName, array_replace_recursive(
            $this->getDefaultSnapper()->toArray(),
            $data->toArray()
        ));
    }

    /**
     * @return DataBag
     */
    private function getDefaultSnapper()
    {
        return $this->data->get('snappers.default', new DataBag());
    }

    public function getSnapper($snapper)
    {
        if (!isset($this->snappers[$snapper]))
        {
            throw new RuntimeException(
              "Snapper \"{$snapper}\""
            );
        }

        return $this->snappers[$snapper];
    }
}