<?php

namespace Datashot\Core;

use Datashot\Util\EventBus;
use PDO;
use PDOStatement;

interface SnapRestorer
{
    const RESTORING = 'restoring_snap';
    const RESTORED  = 'snap_restored';
    const CREATING_DATABASE = 'creating_database';
    const STDOUT = 'restoring_stdout';

    function __construct(EventBus $bus, RestoringSettings $config);

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
}
