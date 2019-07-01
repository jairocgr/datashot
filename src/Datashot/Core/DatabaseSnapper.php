<?php

namespace Datashot\Core;

use PDO;
use PDOStatement;

interface DatabaseSnapper
{
    const START_DUMPING       = 'start_dumping';
    const END_DUMPING         = 'end_dumping';

    const DUMPING_DDL         = 'dumping_ddl';
    const DUMPING_TABLE_DDL   = 'dumping_table_ddl';

    const DUMPING_DATA        = 'dumping_data';
    const DUMPING_TABLE_DATA  = 'dumping_table_data';

    const DUMPING_ACTIONS     = 'dumping_actions';
    const DUMPING_TRIGGERS    = 'dumping_triggers';
    const DUMPING_FUNCTIONS   = 'dumping_functions';
    const DUMPING_PROCEDURES  = 'dumping_procedures';
    const DUMPING_TRIGGER     = 'dumping_trigger';
    const DUMPING_FUNCTION    = 'dumping_function';
    const DUMPING_PROCEDURE   = 'dumping_procedure';

    const DUMPING_VIEWS       = 'dumping_views';
    const DUMPING_VIEW        = 'dumping_view';

    const TABLE_DUMPED       = 'table_dumped';
    const SNAPPED            = 'snapped';
    const CREATING_SNAP_FILE = 'creating_snap_file';
    const SNAPPING           = 'snapping';

    /**
     * Take the snapshot
     */
    function snap(Database $database, SnapLocation $destination);

    /**
     * @return Database
     */
    function getSnappedDatabase();

    /**
     * @return DatabaseServer
     */
    function getSnappedServer();

    /**
     *
     */
    function set($key, $value);

    /**
     * @return mixed
     */
    function get($key, $defaultValue = NULL);

    /**
     * @return bool
     */
    function has($key);

    /**
     * @return PDOStatement
     */
    function query($sql, $args = []);

    /**
     * @return int
     */
    function execute($sql, $args = []);

    /**
     * @return PDO
     */
    function getConnection();

    /**
     * Append a sql command to the snapshot file
     *
     * @param $sql callable|string
     */
    function append($sql);

    /**
     * @param $string
     * @return mixed
     */
    function puts($string);
}