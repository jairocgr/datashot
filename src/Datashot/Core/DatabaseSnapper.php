<?php

namespace Datashot\Core;

use Datashot\Util\EventBus;

interface DatabaseSnapper
{
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
    const SNAPED             = 'snaped';
    const CREATING_SNAP_FILE = 'creating_snap_file';
    const SNAPPING           = 'snapping';

    function __construct(EventBus $bus, SnapperConfiguration $config);

    function snap();

    /**
     * @return string
     */
    function getDatabaseName();

    /**
     * @return DatabaseServer
     */
    function getDatabaseServer();

    /**
     * @return int
     */
    function getDatabasePort();

    /**
     * @return string
     */
    function getDatabaseUser();

    /**
     * @return string
     */
    function getDatabaseHost();
}