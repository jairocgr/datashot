<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;
use RuntimeException;

class Configuration
{
    /**
     * @var DataBag
     */
    private $data;

    /**
     * @var SnapperConfiguration[]
     */
    private $snappers = [];

    /**
     * @var DatabaseServer[]
     */
    private $databaseServers = [];

    /**
     * @var RestoringSettings[]
     */
    private $restoringSettings = [];

    public function __construct(array $data)
    {
        $this->parseConfiguration($data);
    }

    private function parseConfiguration(array $data)
    {
        $this->data = new DataBag($data);

        $this->parseDatabaseServers();
        $this->parseSnappers();
        $this->parseRestoringSettings();
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

            if ($data->exists('extends')) {
                $base = $this->data->get("snappers.{$data->extends}", new DataBag());

                $data = new DataBag(array_replace_recursive(
                    $base->toArray(),
                    $data->toArray()
                ));
            }

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
        if ($data->exists('database_server') && $data->isString('database_server')) {

            $serverName = $data->get('database_server');

            $data->set('database_server', $this->getDatabase($serverName));
        }

        return new SnapperConfiguration($snapperName, $data);
    }

    /**
     * @return SnapperConfiguration
     */
    public function getSnapper($snapper)
    {
        if (!isset($this->snappers[$snapper]))
        {
            throw new RuntimeException(
              "Snapper \"{$snapper}\" not found!"
            );
        }

        return $this->snappers[$snapper];
    }

    /**
     * @return RestoringSettings
     */
    public function getRestoringSettings($snapper, $target)
    {
        if (!isset($this->restoringSettings[$snapper][$target])) {
            throw new RuntimeException(
                "Restoring not found for \"{$snapper}\" to \"{$target}\" database!"
            );
        }

        return $this->restoringSettings[$snapper][$target];
    }

    private function parseRestoringSettings()
    {
        if ($this->data->notExists('restoring_settings')) {
            return;
        }

        $snapers = $this->data->get('restoring_settings');

        foreach ($snapers as $snaper => $databases) {
            foreach ($databases as $database => $data) {

                if (!isset($this->restoringSettings[$snaper])) {
                    $this->restoringSettings[$snaper] = [];
                }

                $this->restoringSettings[$snaper][$database] = new RestoringSettings(
                    $this->getSnapper($snaper),
                    $this->getDatabase($database),
                    $data
                );

            }
        }
    }
}