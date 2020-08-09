<?php

namespace Datashot\Repository;

use Datashot\Core\Snap;
use Datashot\Lang\Asserter;
use Datashot\Lang\DataBag;
use Datashot\Lang\TempFile;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

class SftpRepository extends FilesystemRepo
{
    const DRIVER_HANDLE = 'sftp';
    const TIMEOUT = 10;

    protected function createFilesystem(DataBag $data)
    {
        return new Filesystem(new SftpAdapter([

            'host'       => $this->extractHost($data),
            'port'       => $this->extractPort($data),

            'username'   => $this->extractUser($data),
            'password'   => $this->extractPassword($data),
            'privateKey' => $this->extractPrivateKey($data),

            'root'       => $this->extractRoot($data),
            'timeout'    => static::TIMEOUT,
        ]));
    }

    private function extractHost(DataBag $data)
    {
        return $data->extract('host', function ($value, Asserter $a) {

            if (empty($value)) $a->raise("Require host on :repo repository!", [
                'repo' => $this
            ]);

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid host :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    private function extractPort(DataBag $data)
    {
        return $data->extract('port', 22, function ($value, Asserter $a) {

            if ($a->integerfyable($value)) {
                return intval($value);
            }

            $a->raise("Invalid port :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    private function extractUser(DataBag $data)
    {
        return $data->extract('user', function ($value, Asserter $a) {

            if (empty($value)) $a->raise("Require user on :repo repository!", [
                'repo' => $this
            ]);

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid user :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    private function extractPassword(DataBag $data)
    {
        return $data->extract('password', function ($value, Asserter $a) {

            if ($a->stringfyable($value)) {
                return strval($value);
            }

            $a->raise("Invalid password :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    private function extractPrivateKey(DataBag $data)
    {
        return $data->extract('private_key', function ($value, Asserter $a) {

            if ($a->stringfyable($value)) {
                return strval($value);
            }

            $a->raise("Invalid private_key :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    private function extractRoot(DataBag $data)
    {
        return $data->extract('root', '', function ($value, Asserter $a) {

            if ($a->stringfyable($value)) {
                return strval($value);
            }

            $a->raise("Invalid root :value on :repo repository!", [
                'value' => $value,
                'repo' => $this
            ]);
        });
    }

    /**
     * @inheritDoc
     */
    function getPhysicalPath(Snap $snap)
    {
        $stream = $snap->read();

        $tmp = new TempFile();
        $tmp->sink($stream);

        return $tmp->getPath();
    }
}
