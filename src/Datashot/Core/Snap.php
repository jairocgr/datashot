<?php


namespace Datashot\Core;

use DateTime;
use RuntimeException;

class Snap implements RepositoryItem
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
     * @var int
     */
    private $size;

    /**
     * @var int
     */
    private $timestamp;

    /**
     * Snap constructor.
     * @param Path $path
     * @param SnapRepository $repository
     * @param int $size
     * @param int $timestamp
     */
    public function __construct(Path $path, SnapRepository $repository, $size, $timestamp)
    {
        $this->path = $path;
        $this->repository = $repository;
        $this->size = $size;
        $this->timestamp = $timestamp;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->path->getItemName();
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @return int
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @return DateTime
     */
    public function getDate()
    {
        return $this->wrap($this->timestamp);
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
    public function getHumanSize()
    {
        $bytes = $this->size;
        $size = array('b','kb','mb','gb','tb','pb','eb','zb','yb');
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.0f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getCanonicalDate()
    {
        return $this->getDate()->format("Y-m-d h:i:s");
    }

    private function wrap($timestamp)
    {
        $date = new DateTime();
        $date->setTimestamp($timestamp);

        return $date;
    }

    /**
     * @return boolean
     */
    public function isSnapshot()
    {
        return TRUE;
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
        return FALSE;
    }

    public function rm()
    {
        $this->repository->rm($this);
    }

    public function ls()
    {
        return [ $this ];
    }

    /**
     * @inheritDoc
     */
    public function copyTo(SnapLocation $dst)
    {
        $repo = $dst->getRepository();
        $path = $dst->getPath();

        $source = $this->repository->getResource($this);

        if ($dst->isDirectory()) {
            $repo->write($source, $path->to($this));
        } else {
            $repo->write($source, $path);
        }
    }

    /**
     * @return SnapLocation
     */
    public function getLocation()
    {
        return new SnapLocation($this->repository, $this->path);
    }

    /**
     * @return resource
     */
    public function read()
    {
        return $this->repository->read($this);
    }

    public function hasCharset()
    {
        $line = $this->getFirstPiece();

        return preg_match("/charset [\w\s\-\_\.]+/", $line);
    }

    public function getCharset()
    {
        $line = $this->getFirstPiece();
        $matches = [];

        preg_match("/charset ([\w\d\-\_\.]+)/", $line, $matches);

        return isset($matches[1]) ? $matches[1] : '';
    }

    private function fread($handle, $length)
    {
        if (($res = fread($handle, $length)) !== FALSE) {
            return $res;
        } else {
            throw new RuntimeException("Reading \"{$this}\" failure!");
        }
    }

    private function getFirstPiece()
    {
        $resource = $this->read();

        $piece = $this->fread($resource, 1024);

        return gzdecode($piece);
    }
}