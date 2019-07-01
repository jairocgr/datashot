<?php


namespace Datashot\Core;


interface RepositoryItem
{
    /**
     * @return boolean
     */
    public function isSnapshot();

    /**
     * @return boolean
     */
    public function isDirectory();

    /**
     * @return boolean
     */
    public function isSnapSet();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return SnapRepository
     */
    public function getRepository();

    /**
     * Path inside a repository
     *
     * @return Path
     */
    public function getPath();

    public function rm();

    public function __toString();

    /**
     * @return RepositoryItem[]
     */
    public function ls();

    /**
     * @param $dst SnapLocation
     */
    public function copyTo(SnapLocation $dst);

    /**
     * @return SnapLocation
     */
    public function getLocation();
}