<?php

namespace Datashot\Core;

use Datashot\Util\EventBus;

interface DatabaseSnapper
{
    const DUMPING_SCHEMA      = 'dumping_schema';
    const DUMPING_TABLES      = 'dumping_tables';

    const DUMPING_TABLE_DDL   = 'dumping_table_ddl';
    const DUMPING_TABLE_DATA  = 'dumping_table_data';

    const DUMPING_TRIGGERS    = 'dumping_triggers';
    const DUMPING_FUNCTIONS   = 'dumping_functions';
    const DUMPING_PROCEDURES  = 'dumping_procedures';

    const DUMPING_VIEWS       = 'dumping_views';
    const DUMPING_VIEW        = 'dumping_view';

    const TABLE_DUMPED       = 'table_dumped';
    const SNAPED             = 'snaped';
    const DUMPING_ACTIONS    = 'dumping_actions';
    const CREATING_SNAP_FILE = 'creating_snap_file';
    const SNAPPING           = 'snapping';
    const APPENDING          = 'appending';
    const APPENDED           = 'appended';

    const CONNECTING         = 'connecting';

    function __construct(EventBus $bus, SnapperConfiguration $config);

    function snap();
}