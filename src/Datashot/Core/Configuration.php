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
     * @var DataBag
     */
    private $parameters;

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

    /**
     * @var Repository[]
     */
    private $repositories = [];

    /**
     * @var UploadSettings[]
     */
    private $uploadSettings = [];

    public function __construct(array $data)
    {
        $this->data = new DataBag($data);

        $this->parseParameters();
        $this->parseDatabaseServers();
        $this->parseSnappers();
        $this->parseRestoringSettings();
        $this->parseRepositories();
        $this->parseUploadSettings();
    }

    private function parseDatabaseServers()
    {
        $servers = $this->data->get('database_servers', []);

        foreach ($servers as $server => $config) {

            $config = $this->parameters->combine($config);

            $this->databaseServers[$server] = new DatabaseServer($server, $config);
        }
    }

    private function parseSnappers()
    {
        $this->data->checkIfTransversable("snappers");
        $this->data->checkIfIsACollectionOf("snappers", DataBag::class);

        $snappers = $this->data->get("snappers");

        foreach ($snappers as $snapperName => $data) {

            $data = $this->parameters->combine($data);

            if ($data->exists('extends')) {

                $this->data->checkIfIs("snappers.{$data->extends}", DataBag::class,
                    "{$snapperName} references a invalid snapper \"{$data->extends}\""
                );

                $base = $this->data->get("snappers.{$data->extends}");

                $data = new DataBag(array_replace_recursive(
                    $base->toArray(),
                    $data->toArray()
                ));
            }

            $data->merge($this->parameters);

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

    /**
     * @return Repository
     */
    public function getRepository($name)
    {
        if (!isset($this->repositories[$name])) {
            throw new RuntimeException("Repositories \"{$name}\" not fount!");
        }

        return $this->repositories[$name];
    }

    private function parseSnapper($snapperName, DataBag $data)
    {
        $data->checkIfNotEmpty('database_server', "{$snapperName} must have a :key!");

        if ($data->isString('database_server')) {

            $serverName = $data->get('database_server');

            $data->set('database_server', $this->getDatabase($serverName));

        } elseif ($data->is('database_server', DataBag::class)) {
            $data->set('database_server', new DatabaseServer(
                "$snapperName/server",
                $data->get('database_server')
            ));
        } elseif ($data->is('database_server', DatabaseServer::class)) {
            // Already setted
        } else {
            throw new RuntimeException(
                "Invalid database_server for {$snapperName}"
            );
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

            if (!isset($this->restoringSettings[$snapper])) {
                $this->restoringSettings[$snapper] = [];
            }

            $this->restoringSettings[$snapper][$target] = new RestoringSettings(
                $this->getSnapper($snapper),
                $this->getDatabase($target),
                new DataBag()
            );
        }

        return $this->restoringSettings[$snapper][$target];
    }

    private function parseRestoringSettings()
    {
        $snapers = $this->data->get('restoring_settings', []);

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

    /**
     * @return UploadSettings
     */
    public function getUploadSettings($snapper, $target)
    {
        if (!isset($this->uploadSettings[$snapper][$target])) {

            if (!isset($this->uploadSettings[$snapper])) {
                $this->uploadSettings[$snapper] = [];
            }

            $this->uploadSettings[$snapper][$target] = new UploadSettings(
                $this->getSnapper($snapper),
                $this->getRepository($target),
                new DataBag()
            );
        }

        return $this->uploadSettings[$snapper][$target];
    }

    private function parseRepositories()
    {
        $repositories = $this->data->get('repositories', []);

        foreach ($repositories as $repo => $config) {

            $config = $this->parameters->combine($config);

            $this->repositories[$repo] = new Repository($repo, $config);
        }
    }

    private function parseUploadSettings()
    {
        $uploads = $this->data->get('upload_settings', []);

        foreach ($uploads as $snapper => $repositories) {
            foreach ($repositories as $repo => $data) {

                if (!isset($this->uploadSettings[$snapper])) {
                    $this->uploadSettings[$snapper] = [];
                }

                $this->uploadSettings[$snapper][$repo] = new UploadSettings(
                    $this->getSnapper($snapper),
                    $this->getRepository($repo),
                    $data
                );

            }
        }
    }

    private function parseParameters()
    {
        $this->parameters = $this->data->get('parameters', new DataBag());
    }
}