<?php return [

    'snappers' => [

        'docker_app' => [

            'triggers'  => TRUE,
            'routines'  => TRUE,

            'output_dir' => __DIR__,

            'compress' => TRUE,

            'database_server' => 'docker_mysql56',

            'database_name' => getenv('DOCKER_MYSQL56_DATABASE'),

            // 'where' => 'true limit 1000',
        ],

        'dshot_sql' => [

            'extends' => 'dshot',

            'compress' => FALSE,
        ],

        'dshot' => [

            'triggers'  => TRUE,
            'routines'  => TRUE,

            'output_file' => 'snapped',

            // generic where bring only 2 rows per table
            'where' => 'true order by 1 limit 2',

            'database_server' => 'workbench1',

            'database_name'   => 'datashot',

            'output_dir' => realpath(__DIR__ . '/tests/assets'),

            // Custom made property
            'excluded_user' => 'usr103',

            'wheres' => [

                'logs' => "created_at > '2018-03-01'",

                'users' => function (\Datashot\Core\DatabaseSnapper $snapper) {

                    $excluded = [ $snapper->get('excluded_user') ];
                    $selected = [];

                    $stmt = $snapper->query("SELECT login FROM users WHERE active IS TRUE");

                    foreach ($stmt->fetchAll() as $res) {
                        $selected[] = $res->login;
                    }

                    $selected = "'" . implode("', '", $selected) . "'";
                    $excluded = "'" . implode("', '", $excluded) . "'";

                    return "login IN ({$selected}) and ".
                           "login NOT IN ({$excluded})";
                }
            ],

            'row_transformers' => [
                'users' => function ($row) {

                    // hidding user phone numbers
                    $row->phone = "+55 67 99999-1000";

                    return $row;
                }
            ],

            // Event-based hook
            'before' => function (\Datashot\Core\DatabaseSnapper $snapper) {
                $snapper->append("SELECT 'Restoring snapshot...';");

                return "-- comment\n";
            },

            'after' => function (\Datashot\Core\DatabaseSnapper $snapper) {
                $snapper->append("

                  -- Set default user password
                  SELECT 'Setting user password to default_pw...';
                  UPDATE users SET password = sha1('default_pw');

                ");
            }
        ]
    ],

    'database_servers' => [
        'workbench1' => [
            'driver'    => 'mysql',

            'unix_socket' => getenv('WORKBENCH_SOCKET'),

            'host'      => getenv('WORKBENCH_HOST'),
            'port'      => getenv('WORKBENCH_PORT'),

            'username'  => getenv('WORKBENCH_USER'),
            'password'  => getenv('WORKBENCH_PASSWORD')
        ],

        'docker_mysql56' => [
            'driver'    => 'mysql',

            'host'      => getenv('DOCKER_MYSQL56_HOST'),
            'port'      => getenv('DOCKER_MYSQL56_PORT'),

            'username'  => getenv('DOCKER_MYSQL56_USER'),
            'password'  => getenv('DOCKER_MYSQL56_PASSWORD')
        ]
    ],


    'restoring_settings' => [
        'dshot' => [
            'workbench1' => [
                'database_name' => 'restored_dshot',
            ]
        ],
        'dshot_sql' => [
            'workbench1' => [
                'database_name' => 'restored_dshot_sql',

                'before' => function () {
                    return "create table _test_table (
                      id integer not null auto_increment primary key,
                      handle varchar(24) not null
                    )";
                },

                'after' => function (\Datashot\Core\SnapRestorer $restorer) {
                    $restorer->execute("UPDATE users SET password = sha1('default_pw')");
                }
            ]
        ]
    ]
];
