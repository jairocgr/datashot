<?php

namespace Datashot;

use Closure;
use Datashot\Core\DatabaseServer;
use Datashot\Core\DatabaseSnapper;
use Datashot\Core\EventBus;
use Datashot\Core\RepositoryItem;
use Datashot\Core\SnapRepository;
use Datashot\Core\Shell;
use Datashot\Core\Snap;
use Datashot\Core\Path;
use Datashot\Core\SnapperConfiguration;
use Datashot\Core\SnapRestorer;
use Datashot\Core\SnapLocation;
use Datashot\Core\SnapUploader;
use Datashot\Lang\Asserter;
use Datashot\Lang\DataBag;
use Datashot\Mysql\MysqlDatabaseServer;
use Datashot\Mysql\MysqlDatabaseSnapper;
use Datashot\Mysql\MysqlSnapRestorer;
use Datashot\Repository\CwdRepository;
use Datashot\Repository\LocalRepository;
use Datashot\Repository\S3Repository;
use Datashot\Repository\SftpRepository;
use Datashot\S3\S3SnapUploader;
use InvalidArgumentException;
use RuntimeException;

class Datashot
{
    const OUTPUT = 'output';

    public static function getVersion()
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'));

        return $composer->version;
    }

    public static function getPackageName()
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'));

        return $composer->name;
    }

    public static function getPackageUrl()
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../composer.json'));

        return $composer->homepage;
    }

    /**
     * @var DataBag
     */
    private $config;

    /**
     * @var EventBus
     */
    private $bus;

    /**
     * @var Shell
     */
    private $shell;

    /**
     * @var DatabaseServer[]
     */
    private $databaseServers = [];

    /**
     * @var SnapperConfiguration[]
     */
    private $snappers = [];

    /**
     * @var DataBag
     */
    private $args;

    /**
     * @var SnapRepository[]
     */
    private $repositories;

    /**
     * @var SnapRepository
     */
    private $defaultRepository;

    /**
     * @var SnapperConfiguration
     */
    private $defaultSnapper;

    public function __construct(array $config)
    {
        $this->bus = new EventBus();
        $this->shell = new Shell();
        $this->config = $this->wrap($config);

        $this->parseDatabaseServers();
        $this->parseSnappers();
        $this->parseRepositories();

        $this->defaultRepository = new CwdRepository();
        $this->defaultSnapper = new SnapperConfiguration('default', new DataBag());
    }

    private function parseSnappers()
    {
        $snappers = $this->config->extract("snappers", function ($value, Asserter $a) {

            if ($a->transversable($value) && $a->collectionOf($value, DataBag::class)) {
                return $value;
            }

            $a->raise("Invalid snappers!");
        });

        foreach ($snappers as $snapperName => $data) {

            if ($data->exists('extends')) {

                $base = $this->config->extract("snappers.{$data->extends}", function ($value, Asserter $a) use ($snapperName, $data) {

                    if ($value instanceof DataBag) {
                        return $value;
                    }

                    $a->raise("{$snapperName} references a invalid snapper \"{$data->extends}\"!");
                });

                $data = new DataBag(array_replace_recursive(
                    $base->toArray(),
                    $data->toArray()
                ));
            }

            $this->snappers[$snapperName] = new SnapperConfiguration($snapperName, $data);
        }
    }

    private function parseDatabaseServers()
    {
        $servers = $this->config->extract('database_servers', function ($value, Asserter $a) {

            if (empty($value)) $a->raise("Require at least one database server!");

            if ($a->transversable($value) && $a->collectionOf($value, DataBag::class)) {
                return $value;
            }

            $a->raise("Invalid database_servers!");
        });

        foreach ($servers as $server => $config) {
            $this->databaseServers[$server] = $this->buildDatabaseServer($server, $config);
        }
    }

    private function parseRepositories()
    {
        $repositories = $this->config->extract('repositories', [], function ($value, Asserter $a) {

            if ($a->transversable($value) && (empty($value) || $a->collectionOf($value, DataBag::class))) {
                return $value;
            }

            $a->raise("Invalid repositories!");
        });

        foreach ($repositories as $repo => $config) {
            $this->repositories[$repo] = $this->buildRepository($repo, $config);
        }
    }

    private function wrap(array $data)
    {
        if (empty($data)) {
            throw new RuntimeException("Configuration array cannot be empty!");
        }

        return new DataBag($data);
    }

    private function buildDatabaseServer($name, DataBag $config)
    {
        $driver = $config->extract('driver', function ($value, Asserter $a) use ($name) {

            if (empty($value)) $a->raise("Require driver on :db server!", [
                'db' => $name
            ]);

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid driver :value on :db server!", [
                'value' => $value,
                'db' => $name
            ]);
        });

        if ($driver == MysqlDatabaseServer::DRIVER_HANDLE) {
            return new MysqlDatabaseServer($name, $config, $this->bus, $this->shell);
        }

        throw new InvalidArgumentException(
            "Invalid database driver \"{$driver}\"!"
        );
    }

    private function buildRepository($name, DataBag $config)
    {
        $driver = $config->extract('driver', function ($value, Asserter $a) use ($name) {

            if (empty($value)) $a->raise("Require driver on :repo repository!", [
                'repo' => $name
            ]);

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid driver :value on :repo repository!", [
                'value' => $value,
                'repo' => $name
            ]);
        });

        if ($driver == LocalRepository::DRIVER_HANDLE) {
            return new LocalRepository($name, $config);
        }

        if ($driver == S3Repository::DRIVER_HANDLE) {
            return new S3Repository($name, $config);
        }

        if ($driver == SftpRepository::DRIVER_HANDLE) {
            return new SftpRepository($name, $config);
        }

        throw new InvalidArgumentException(
            "Invalid repository driver \"{$driver}\"!"
        );
    }

    public function snap($spec)
    {
        $snapper = $this->buildSnapperFor($spec);

        $snapper->snap();
    }

    /**
     * @return DatabaseSnapper
     */
    private function buildSnapperFor($snapper)
    {
        $snapper = $this->config->getSnapper($snapper);

        $driver = $snapper->getDriver();

        switch ($driver) {
            case 'mysql':

                return new MysqlDatabaseSnapper($this->bus, $this->shell, $snapper);

                break;
            default:
                throw new RuntimeException(
                    "Insuported \"{$driver}\" database driver"
                );
        }
    }

    public function restore($snapper, $target)
    {
        $restore = $this->buildRestorer($snapper, $target);

        $restore->restore();
    }

    /**
     * @return SnapRestorer
     */
    private function buildRestorer($snapper, $target)
    {
        $settings = $this->config->getRestoringSettings($snapper, $target);

        $driver = $settings->getDriver();

        switch ($driver) {
            case 'mysql':

                return new MysqlSnapRestorer($this->bus, $this->shell, $settings);

                break;
            default:
                throw new RuntimeException(
                    "Insuported \"{$driver}\" database driver"
                );
        }
    }

    public function upload($snapper, $target)
    {
        $restore = $this->buildUploader($snapper, $target);

        $restore->upload();
    }

    /**
     * @return SnapUploader
     */
    private function buildUploader($snapper, $target)
    {
        $settings = $this->config->getUploadSettings($snapper, $target);

        $driver = $settings->getDriver();

        switch ($driver) {
            case 's3':

                return new S3SnapUploader($this->bus, $settings);

                break;
            default:
                throw new RuntimeException(
                    "Insuported \"{$driver}\" repository driver"
                );
        }
    }

    public function on($event, Closure $callback)
    {
        $this->bus->on($event, $callback);
    }

    /**
     * @return DatabaseServer
     */
    public function getServer($server)
    {
        if (!isset($this->databaseServers[$server])) {
            throw new InvalidArgumentException(
                "Database server \"{$server}\" not found!"
            );
        }

        return $this->databaseServers[$server];
    }

    public function run($task, $args = [])
    {
        $closure = $this->findTask($task);

        $this->setArguments($args);

        call_user_func($closure, $this);
    }

    private function setArguments($args)
    {
        $this->args = new DataBag($args);
    }

    public function arg($name, $defaultValue = NULL)
    {
        return $this->args->get($name, $defaultValue);
    }

    private function findTask($task)
    {
        return $this->config->extract($task, NULL, function ($value) {

            if ($value == NULL) throw new InvalidArgumentException(
                "Task \"{$value}\" not found!"
            );

            if (!is_callable($value)) throw new InvalidArgumentException(
                "Invalid \"{$value}\" task!"
            );

            return $value;
        });
    }

    public function puts($message)
    {
        $this->bus->publish(static::OUTPUT, $message);
    }

    /**
     * @return SnapperConfiguration
     */
    public function getSnapperConfiguration($snapper = NULL)
    {
        if ($snapper == NULL) {
            return $this->defaultSnapper;
        }

        if (!isset($this->snappers[$snapper]))
        {
            throw new RuntimeException(
                "Snapper \"{$snapper}\" not found!"
            );
        }

        return $this->snappers[$snapper];
    }


    /**
     * @return SnapRepository
     */
    private function getRepository($repo)
    {
        if (!isset($this->repositories[$repo]))
        {
            throw new RuntimeException(
                "Repository \"{$repo}\" not found!"
            );
        }

        return $this->repositories[$repo];
    }

    /**
     * @param $path
     * @return RepositoryItem[]
     */
    public function ls($path)
    {
        $locator = $this->parse($path);

        $repo = $locator->getRepository();
        $path = $locator->getPath();

        return $repo->ls($path);
    }

    /**
     * @param $path
     * @return RepositoryItem
     */
    public function get($path)
    {
        $locator = $this->parse($path);

        $repo = $locator->getRepository();
        $path = $locator->getPath();

        return $repo->get($path);
    }

    public function mkdir($path)
    {
        $locator = $this->parse($path);

        $repo = $locator->getRepository();
        $path = $locator->getPath();

        return $repo->mkdir($path);
    }

    /**
     * @return SnapLocation
     */
    public function parse($path)
    {
        $path = $this->normalizePath($path);

        $matches = [];

        if (preg_match("/^([a-z][\w\d\-\_\.]*)(:.*)?$/i", $path, $matches)) {
            $repo = $matches[1];

            if (isset($matches[2])) {
                $subPath = $matches[2];
                $subPath = ltrim($subPath, ':');
            } else {
                $subPath = '';
            }

            if ($this->repositoryExist($repo)) {
                return new SnapLocation($this->getRepository($repo), new Path($subPath));
            } elseif (empty($subPath)) {
                // If repository don't exist and path is empty, this may mean
                // that the repo itself is a directory or snapshot in the current dir
                return new SnapLocation($this->defaultRepository, new Path($repo));
            } else {
                // Then the user is trying to list something inside a invalid
                // snapshot repo
                throw new InvalidArgumentException(
                    "Repository \"{$repo}\" not found!"
                );
            }

        } else {
            return new SnapLocation($this->defaultRepository, new Path($path));
        }
    }

    private function repositoryExist($repo)
    {
        return isset($this->repositories[$repo]);
    }

    private function normalizePath($path)
    {
        $path = trim(strval($path));

        // Normalize back-slashes to forward slashes
        $path = str_replace('\\', '/', $path);

        // Normalize multiples forward slashes. Usually cause by
        // ill made concatenations
        $path = preg_replace("/([\/]{2,})/", '/', $path);

        $path = $this->removeFunkyWhiteSpace($path);

        return $path;
    }

    /**
     * Removes unprintable characters and invalid unicode characters.
     */
    private function removeFunkyWhiteSpace($path) {
        // We do this check in a loop, since removing invalid unicode characters
        // can lead to new characters being created.
        while (preg_match('#\p{C}+|^\./#u', $path)) {
            $path = preg_replace('#\p{C}+|^\./#u', '', $path);
        }

        return $path;
    }

    /**
     * @param $path string
     * @return Snap[]
     */
    public function findSnaps($path)
    {
        $item = $this->get($path);
        $snaps = [];

        foreach ($item->ls() as $entry) {
            if ($entry->isSnapshot()) {
                $snaps[] = $entry;
            }
        }

        if (!empty($snaps)) {
            return $snaps;
        }

        else throw new InvalidArgumentException(
            "Snapshot \"{$path}\" not found!"
        );
    }
}
