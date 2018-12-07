<?php

namespace Datashot;

use Datashot\Core\Configuration;
use Datashot\Core\DatabaseSnapper;
use Datashot\Mysql\MysqlDatabaseSnapper;
use Datashot\Util\EventBus;
use RuntimeException;

class Datashot
{
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

    /** @var Configuration */
    private $config;

    /** @var EventBus */
    private $bus;

    public function __construct(EventBus $bus, array $config)
    {
        $this->bus = $bus;
        $this->config = new Configuration($config);
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

                return new MysqlDatabaseSnapper($this->bus, $snapper);

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

    private function buildRestorer($snapper, $target)
    {
        $config = $this->config->getRestorer($snapper, $target);
    }
}