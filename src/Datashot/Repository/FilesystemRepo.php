<?php


namespace Datashot\Repository;

use Datashot\Core\Directory;
use Datashot\Core\Path;
use Datashot\Core\RepositoryItem;
use Datashot\Core\SnapRepository;
use Datashot\Core\Snap;
use Datashot\Core\SnapSet;
use Datashot\Lang\DataBag;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use RuntimeException;


abstract class FilesystemRepo implements SnapRepository
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var Filesystem
     */
    protected $fs;

    public function __construct($name, DataBag $data)
    {
        $this->name = $this->validateName($name);
        $this->fs = $this->createFilesystem($data);
    }

    /**
     * @inheritDoc
     */
    public function get(Path $path)
    {
        if ($this->hasDirectory($path)) {
            // That means that path point to a existing directory
            return new Directory($path, $this);
        }

        elseif ($this->hasSnap($path)) {
            // Found a snapshot
            return $this->getSnap($path);
        }

        elseif ($path->isGlobPattern()) {
            // Means that path may be pointing to a set of snaps
            $directory = $path->getSubPath();

            if ($this->hasDirectory($directory)) {
                return new SnapSet($path, $this);
            }
        }

        throw new InvalidArgumentException(
            "Path \"{$path}\" not found!"
        );
    }

    /**
     * @inheritDoc
     */
    public function ls(Path $path)
    {
        if (!$this->hasDirectory($path)) throw new InvalidArgumentException(
            "Path \"{$path}\" not found!"
        );

        $content = $this->fs->listContents($path);
        $items = [];

        foreach ($content as $entry)
        {
            if ($entry['type'] == 'file')
            {
                if (isset($entry['extension']) && $entry['extension'] == 'gz') {
                    $name = $entry['filename'];
                    $size = intval($entry['size']);
                    $timestamp = intval($entry['timestamp']);
                    $snapPath = $path->to($name);

                    $items[] = new Snap($snapPath, $this, $size, $timestamp);
                }
            } elseif ($entry['type'] == 'dir') {
                $dirname = $entry['basename'];
                $dirpath = $path->to($dirname);
                $items[] = new Directory($dirpath, $this);
            }
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function mkdir(Path $path)
    {
        $this->fs->createDir($path);
    }

    /**
     * @inheritDoc
     */
    public function lookup(Path $path, $pattern)
    {
        $result = [];

        foreach ($this->ls($path) as $item)
        {
            if ($item->isSnapshot())
            {
                if (fnmatch($pattern, $item->getName()))
                {
                    $result[] = $item;
                }
            }
        }

        return $result;
    }

    function hasDir(Path $path)
    {
        return $path->pointingToRoot() || $this->fs->has($path);
    }

    public function rm(RepositoryItem $item)
    {
        if ($item->isDirectory()) {
            $this->fs->deleteDir($item->getPath());
        }

        elseif ($item->isSnapshot()) {
            $this->fs->delete("{$item->getPath()}.gz");
        }

        elseif ($item->isSnapSet()) {
            foreach ($item->ls() as $i) {
                $i->rm();
            }
        }
    }

    public function __toString()
    {
        return $this->name;
    }

    function read(Snap $snap)
    {
        return $this->fs->readStream("{$snap->getPath()}.gz");
    }

    private function validateName($name)
    {
        if (is_string($name) && preg_match("/^([a-z][\w\d\-\_\.]*)$/i", $name)) {
            return $name;
        }

        else throw new InvalidArgumentException(
            "Invalid repository name \"{$name}\"! Only letters, dashes, underscores, dots, and numbers."
        );
    }

    private function normalizePath($path)
    {
        $path = trim(strval($path));

        // Normalize back-slashes to forward slashes
        $path = str_replace('\\', '/', $path);

        // Normalize multiples forward slashes. Usually cause by
        // ill made concatenations
        $path = preg_replace("/([\/]{2,})/", '/', $path);

        // Remove extension
        $path = preg_replace("/\.gz$/i", '', $path);

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
     * @param $path Path
     * @return bool
     */
    private function hasDirectory($path)
    {
        if ($path->pointingToRoot()) {
            return TRUE;
        } else {
            return $this->fs->has($path);
        }
    }

    /**
     * @param $path Path
     * @return bool
     */
    private function hasSnap($path)
    {
        return $this->fs->has("{$path}.gz");
    }

    /**
     * @return Filesystem
     */
    abstract protected function createFilesystem(DataBag $data);

    private function gzipFile(\League\Flysystem\File $item)
    {
        return in_array($item->getMimetype(), [
            'application/gzip',
            'application/gunzip',
            'application/x-gzip',
            'application/x-gunzip',
            'application/gzipped',
            'application/gzip-compressed',
            'application/x-compressed',
            'application/x-compress',
            'gzip/document',
            'application/octet-stream'
        ]);
    }

    /**
     * @param Path $path
     * @return Snap
     */
    private function getSnap(Path $path)
    {
        $directory = $path->getSubPath();
        $searched = $path->getItemName();

        foreach ($this->ls($directory) as $item)
        {
            if ($item->isSnapshot())
            {
                if ($item->getName() == $searched)
                {
                    return $item;
                }
            }
        }
    }

    function getResource(RepositoryItem $item)
    {
        $path = $item->getPath();

        return $this->fs->readStream("{$path}.gz");
    }

    function write($resource, Path $path)
    {
        if (is_string($resource)) {
            $resource = $this->open($resource);
        }

        return $this->fs->putStream("{$path}.gz", $resource);
    }

    private function getType($path)
    {
        return ($this->fs->getMetadata($path))['type'];
    }

    /**
     * @param $filepath string
     * @return resource
     */
    private function open($filepath)
    {
        if (($handle = fopen($filepath, 'r'))  === FALSE) {
            throw new RuntimeException("Can not open file \"{$handle}\"!");
        }

        stream_set_write_buffer($handle, 4096); // 4KB buffer size

        return $handle;
    }
}