<?php

namespace Datashot\Core;

use PDOStatement;

interface Database
{
    /**
     * Get the database's name
     *
     * @return string
     */
    function getName();

    /**
     * Magical string conversion method
     *
     * @return string
     */
    function __toString();

    /**
     * Run the command in the database
     *
     * @param $command string|resource
     */
    function run($command);

    /**
     * Check if the pattern matches the database name
     *
     * @param $pattern
     * @return boolean
     */
    function matches($pattern);

    /**
     * Snapshot the database
     *
     * @param SnapLocation $output
     * @return Snap
     */
    function snap(SnapperConfiguration $config, SnapLocation $output);

    /**
     * Restore the database snap
     *
     * @param Snap $snap
     */
    function restore(Snap $snap);

    /**
     * Replicate the database to
     *
     * @param DatabaseServer $target
     * @param string|null $name
     */
    function replicateTo(DatabaseServer $target, $name);

    /**
     * Execute the command and return the number of modified lines
     *
     * @param $command string
     * @return int
     */
    function exec($command);

    /**
     * @return PDOStatement
     */
    function query($sql);
}
