<?php

namespace Datashot\Core;

interface SnapRepository
{
    /**
     * @param $path Path
     * @return RepositoryItem
     */
    function get(Path $path);

    /**
     * @param $path Path
     * @return RepositoryItem[]
     */
    function ls(Path $path);

    /**
     * @param $path Path
     * @param $pattern string
     * @return RepositoryItem[]
     */
    function lookup(Path $path, $pattern);

    function rm(RepositoryItem $item);

    function mkdir(Path $path);

    /**
     * @param RepositoryItem $item
     * @return resource
     */
    function getResource(RepositoryItem $item);

    /**
     * @param $resource resource|string
     * @param Path $path
     * @return boolean
     */
    function write($resource, Path $path);

    /**
     * @return boolean
     */
    function hasDir(Path $path);

    /**
     * @return string
     */
    function __toString();

    /**
     * @param Snap $snap
     * @return resource
     */
    function read(Snap $snap);
}