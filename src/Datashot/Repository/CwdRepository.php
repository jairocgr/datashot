<?php


namespace Datashot\Repository;

use Datashot\Core\Path;
use Datashot\Core\RepositoryItem;
use Datashot\Core\Snap;
use Datashot\Core\SnapRepository;
use Datashot\Lang\DataBag;
use League\Flysystem\Filesystem;

class CwdRepository implements SnapRepository
{
    /**
     * @var Filesystem
     */
    private $relativeRepo;

    /**
     * @var Filesystem
     */
    private $absoluteRepo;

    /**
     * @inheritDoc
     */
    function get(Path $path)
    {
        return $this->repo($path)->get($path);
    }

    /**
     * @inheritDoc
     */
    function ls(Path $path)
    {
        return $this->repo($path)->ls($path);
    }

    /**
     * @inheritDoc
     */
    function lookup(Path $path, $pattern)
    {
        return $this->repo($path)->lookup($path, $pattern);
    }

    /**
     * @inheritDoc
     */
    function rm(RepositoryItem $item)
    {
        $this->repo($item->getPath())->rm($item);
    }

    /**
     * @inheritDoc
     */
    function mkdir(Path $path)
    {
        $this->repo($path)->mkdir($path);
    }

    /**
     * @inheritDoc
     */
    function getResource(RepositoryItem $item)
    {
        return $this->repo($item->getPath())->getResource($item);
    }

    /**
     * @inheritDoc
     */
    function write($resource, Path $path)
    {
        return $this->repo($path)->write($resource, $path);
    }

    /**
     * @inheritDoc
     */
    function hasDir(Path $path)
    {
        return $this->repo($path)->hasDir($path);
    }

    /**
     * @inheritDoc
     */
    function read(Snap $snap)
    {
        return $this->repo($snap->getPath())->read($snap);
    }

    /**
     * @param $path Path
     * @return SnapRepository
     */
    private function repo(Path $path)
    {
        if ($path->absolute()) {
            // If the path refeer to the root of the local filesystem,
            // them we must set the local adapter to root, since the library
            // restricts outside root path
            return $this->absoluteRepo();
        } else {
            // Else the path is relative to the current work directory
            return $this->relativeRepo();
        }
    }

    private function absoluteRepo()
    {
        if ($this->absoluteRepo == NULL) {
            $this->absoluteRepo = new LocalRepository('root', new DataBag([
                'path' => '/'
            ]));
        }

        return $this->absoluteRepo;
    }

    private function relativeRepo()
    {
        if ($this->relativeRepo == NULL) {
            $this->relativeRepo = new LocalRepository('cwd', new DataBag([
                'path' => getcwd()
            ]));
        }

        return $this->relativeRepo;
    }

    /**
     * @return string
     */
    function __toString()
    {
        return 'cwd';
    }
}