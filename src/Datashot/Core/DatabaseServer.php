<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;
use PDOStatement;

interface DatabaseServer
{
    /**
     * Get the server's name
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
     * @param $patterns string|string[]
     * @return Database[]
     */
    function lookupDatabases($patterns);

    /**
     * @param $database string
     * @param DataBag $args
     * @return Database
     */
    function createDatabase($database, DataBag $args);

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

    /**
     * Run the command in the server
     *
     * @param $command string|resource
     */
    function run($command);

    /**
     * @return boolean
     */
    function isProduction();

    /**
     * @return boolean
     */
    function isDevelopment();

    /**
     * Check if is possible/allowed to replicate to the target server
     *
     * @param DatabaseServer $target
     * @return bool
     */
    function canReplicateTo(DatabaseServer $target);
}
