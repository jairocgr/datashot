<?php

namespace Datashot\Repository;

use Datashot\Core\Snap;
use Datashot\Lang\Asserter;
use Datashot\Lang\DataBag;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class LocalRepository extends FilesystemRepo
{
    const DRIVER_HANDLE = 'fs';

    /**
     * @var string
     */
    private $path;

    protected function createFilesystem(DataBag $data)
    {
        $this->path = $this->extractPath($data);

        return new Filesystem(new Local(
            $this->path,
            LOCK_EX,
            Local::SKIP_LINKS
        ));
    }

    private function extractPath(DataBag $data)
    {
        return $data->extract('path', function ($value, Asserter $a) {

            if (empty($value)) $a->raise("Require path on :repo repository!", [
                'repo' => $this
            ]);

            if ($a->stringfyable($value) && $a->notEmptyString($value)) {
                return strval($value);
            }

            $a->raise("Invalid path :value on :repo repository!", [
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
        return realpath("{$this->path}/{$snap->getPath()}.gz");
    }
}
