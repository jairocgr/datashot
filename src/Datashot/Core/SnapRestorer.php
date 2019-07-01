<?php

namespace Datashot\Core;

use PDO;
use PDOStatement;

interface SnapRestorer
{
    const RESTORING = 'restoring_snap';
    const RESTORED  = 'snap_restored';
    const CREATING_DATABASE = 'creating_database';
    const STDOUT = 'restoring_stdout';

    function restore();

    /**
     * @return DatabaseServer
     */
    function getTargetDatabase();

    /**
     * @return SnapperConfiguration
     */
    function getSourceSnapper();

    /**
     * @return string
     */
    function getSourceFileName();

    /**
     * @return string
     */
    function getTargetDatabaseName();

    /**
     * @return string
     */
    function getDatabaseCharset();

    /**
     * @return string
     */
    function getDatabaseCollation();

    /**
     * @return int
     */
    function execute($sql, $args = []);

    /**
     * @return PDOStatement
     */
    function query($sql, $args = []);

    /**
     * @return PDO
     */
    function getConnection();

    function puts($string);

    function set($key, $value);

    /**
     * @return mixed
     */
    function get($key, $defaultValue = NULL);
}
