<?php


namespace Datashot\Core;

use RuntimeException;

class SnapLocation
{
    /**
     * @var SnapRepository
     */
    private $repository;

    /**
     * @var Path
     */
    private $path;

    /**
     * SnapRL constructor.
     * @param SnapRepository $repository
     * @param Path $path
     */
    public function __construct(SnapRepository $repository, Path $path)
    {
        $this->repository = $repository;
        $this->path = $path;
    }

    /**
     * @return SnapRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @return Path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        if ($this->path->isEmpty()) {
            return "{$this->repository}";
        } else {
            return "{$this->repository}:{$this->path}";
        }
    }

    public function isDirectory()
    {
        return $this->repository->hasDir($this->path);
    }

    /**
     * @param $dirname string
     * @return SnapLocation
     */
    public function createDir($dirname)
    {
        $path = $this->path->to($dirname);

        $this->repository->mkdir($path);

        return new SnapLocation($this->repository, $path);
    }

    public function aspirate($sourcePath)
    {
        $this->repository->write($sourcePath, $this->path);
        unlink($sourcePath);
    }

    public function to($database)
    {
        return new SnapLocation($this->repository, $this->path->to($database));
    }

    public function toSnap()
    {
        $item = $this->repository->get($this->path);

        if ($item->isSnapshot()) {
            return $item;
        } else {
            throw new RuntimeException(
                "\"{$this}\" is not a single snapshot path!"
            );
        }
    }
}
