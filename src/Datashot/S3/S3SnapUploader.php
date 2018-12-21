<?php

namespace Datashot\S3;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Datashot\Core\Repository;
use Datashot\Core\SnapUploader;
use Datashot\Core\UploadSettings;
use Datashot\Lang\DataBag;
use Datashot\Util\EventBus;

class S3SnapUploader implements SnapUploader
{
    /**
     * @var EventBus
     */
    private $bus;

    /**
     * @var UploadSettings
     */
    private $config;

    public function __construct(EventBus $bus, UploadSettings $config)
    {
        $this->bus = $bus;
        $this->config = $config;
    }

    public function upload()
    {
        $this->publish(SnapUploader::UPLOADING);

        $source = $this->config->getSourceFilePath();

        $repo = $this->config->getTargetRepository();

        $client = S3Client::factory([
            'version' => 'latest',
            'region' => $repo->get('region'),

            'key' => $repo->get('credentials.key'),
            'secret' => $repo->get('credentials.secret')
        ]);

        $start = microtime(true);

        $client->putObject([
            'Key' => $this->getSourceFileName(),
            'Bucket' => $this->getUploadPath(),
            'SourceFile' => $source
        ]);

        $end = microtime(true);

        $this->publish(SnapUploader::DONE, [
            'time' => ($end - $start)
        ]);
    }

    private function publish($event, array $data = [])
    {
        $this->bus->publish($event, $this, new DataBag($data));
    }

    /**
     * @return string
     */
    function getSourceFileName()
    {
        return $this->config->getSourceFileName();
    }

    /**
     * @return Repository
     */
    function getTargetRepository()
    {
        return $this->config->getTargetRepository();
    }

    private function getUploadPath()
    {
        $path = $this->getTargetBucket(). '/' .
                $this->config->getTargetFolder();

        return rtrim($path, " \t\n\r\0\x0B\/");
    }

    private function getTargetBucket()
    {
        $repo = $this->config->getTargetRepository();

        $target = $repo->get('bucket', '/');

        return $this->config->get('bucket', $target);
    }
}