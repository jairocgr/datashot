<?php

namespace Datashot\Repository;

use Aws\S3\S3Client;
use Datashot\Core\Snap;
use Datashot\Lang\Asserter;
use Datashot\Lang\DataBag;
use Datashot\Lang\TempFile;
use InvalidArgumentException;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;

class S3Repository extends FilesystemRepo
{
    const DRIVER_HANDLE = 's3';

    protected function createFilesystem(DataBag $data)
    {
        $s3args = [
            'region' => $this->extractRegion($data),
            'version' => 'latest',
        ];

        if ($data->exists('profile')) {
            $s3args['profile'] = $this->extractProfile($data);
        }

        if ($data->exists('access_key')) {

            if (isset($s3args['profile'])) {
                // If using access_key authentication, we must make sure that
                // the profile argument is not carry forward to S3Client
                //
                // If profile is informed, S3Client will try to use the
                // ~/.aws/credentials file
                //
                unset($s3args['profile']);
            }

            $s3args['credentials'] = [
                'key'    => $this->extractAccessKey($data),
                'secret' => $this->extractSecretKey($data)
            ];
        }

        if ($data->exists('base_path')) {
            $prefix = $this->extractBasePath($data);
        } else {
            $prefix = '';
        }

        $bucket = $this->extractBucket($data);

        return new Filesystem(new AwsS3Adapter(
            new S3Client($s3args),
            $bucket,
            $prefix
        ));
    }

    private function extractRegion(DataBag $data)
    {
        return $data->extract('region', function ($value, Asserter $a) {

            if (empty($value)) $a->raise("Require region on :repo repository!", [
                'repo' => $this
            ]);

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid region :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    private function extractAccessKey(DataBag $data)
    {
        return $data->extract('access_key', function ($value, Asserter $a) {

            if (empty($value)) $a->raise("Require access_key on :repo repository!", [
                'repo' => $this
            ]);

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid access_key :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    private function extractSecretKey(DataBag $data)
    {
        return $data->extract('secret_key', function ($value, Asserter $a) {

            if (empty($value)) $a->raise("Require secret_key on :repo repository!", [
                'repo' => $this
            ]);

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid secret_key :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    private function extractProfile(DataBag $data)
    {
        return $data->extract('profile', function ($value, Asserter $a) {

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid profile :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    private function extractBucket(DataBag $data)
    {
        return $data->extract('bucket', function ($value, Asserter $a) {

            if (empty($value)) $a->raise("Require bucket on :repo repository!", [
                'repo' => $this
            ]);

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid bucket :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    private function extractBasePath(DataBag $data)
    {
        return $data->extract('base_path', '', function ($value, Asserter $a) {

            if ($a->stringfyable($value)) {
                return strval($value);
            }

            throw new InvalidArgumentException(
                "Invalid base_path {$a->vdump($value)} on \"{$this}\" repository!"
            );
        });
    }

    /**
     * @inheritDoc
     */
    function getPhysicalPath(Snap $snap)
    {
        $stream = $this->read($snap);

        $tmp = new TempFile();
        $tmp->sink($stream);

        return $tmp->getPath();
    }
}
