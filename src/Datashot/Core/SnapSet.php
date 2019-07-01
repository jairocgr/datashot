<?php


namespace Datashot\Core;


use RuntimeException;

class SnapSet implements RepositoryItem
{
    /**
     * @var Path
     */
    private $path;

    /**
     * @var SnapRepository
     */
    private $repository;

    /**
     * SnapSet constructor.
     * @param Path $path
     * @param SnapRepository $repository
     * @param Snap[] $snaps
     */
    public function __construct(Path $path, SnapRepository $repository)
    {
        $this->path = $path;
        $this->repository = $repository;
    }

    /**
     * @return Path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return SnapRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    public function rm()
    {
        $this->repository->rm($this);
    }

    /**
     * @return boolean
     */
    public function isSnapshot()
    {
        return FALSE;
    }

    /**
     * @return boolean
     */
    public function isDirectory()
    {
        return FALSE;
    }

    /**
     * @return boolean
     */
    public function isSnapSet()
    {
        return TRUE;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->path->getItemName();
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function ls()
    {
        $dir = $this->path->getSubPath();
        $pattern = $this->path->getGlobPattern();

        return $this->repository->lookup($dir, $pattern);
    }

    /**
     * @inheritDoc
     */
    public function copyTo(SnapLocation $dst)
    {
        foreach ($this->ls() as $item) {
            $item->copyTo($dst);
        }
    }

    /**
     * @inheritDoc
     */
    public function getLocation()
    {
        return new SnapLocation($this->repository, $this->path);
    }
}