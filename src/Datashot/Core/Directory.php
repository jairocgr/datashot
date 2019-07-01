<?php


namespace Datashot\Core;


class Directory implements RepositoryItem
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
     * Directory constructor.
     * @param Path $path
     * @param SnapRepository $repository
     */
    public function __construct(Path $path, SnapRepository $repository)
    {
        $this->path = $path;
        $this->repository = $repository;
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
    public function getName()
    {
        return $this->path->getItemName();
    }

    public function __toString()
    {
        return $this->getName();
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
    public function isSnapSet()
    {
        return FALSE;
    }

    /**
     * @return boolean
     */
    public function isDirectory()
    {
        return TRUE;
    }

    public function rm()
    {
        $this->repository->rm($this);
    }

    public function ls()
    {
        return $this->repository->ls($this->path);
    }

    /**
     * @inheritDoc
     */
    public function copyTo(SnapLocation $dst)
    {
        $dst = $dst->createDir($this);

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