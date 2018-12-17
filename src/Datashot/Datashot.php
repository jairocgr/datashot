<?php

namespace Datashot;

use Datashot\Core\Configuration;
use Datashot\Core\DatabaseSnapper;
use Datashot\Core\SnapRestorer;
use Datashot\Core\SnapUploader;
use Datashot\Mysql\MysqlDatabaseSnapper;
use Datashot\Mysql\MysqlSnapRestorer;
use Datashot\S3\S3SnapUploader;
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

    /**
     * @return SnapRestorer
     */
    private function buildRestorer($snapper, $target)
    {
        $settings = $this->config->getRestoringSettings($snapper, $target);

        $driver = $settings->getDriver();

        switch ($driver) {
            case 'mysql':

                return new MysqlSnapRestorer($this->bus, $settings);

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
}