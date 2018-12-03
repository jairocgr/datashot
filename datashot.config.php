<?php return [

    'snappers' => [

        'docker_app' => [

            'triggers'  => TRUE,
            'routines'  => TRUE,

            'output_dir' => __DIR__,

            'compress' => FALSE,

            'database_server' => 'docker_mysql56',

            'database_name' => getenv('DOCKER_MYSQL56_DATABASE'),

            // 'where' => 'true limit 1000',
        ],

        'crm_sql' => [

            'extends' => 'crm',

            'compress' => FALSE,
        ],

        'crm' => [

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

                'log' => "created_at > '2018-03-01'",

                'users' => function (PDO $pdo, \Datashot\Core\SnapperConfiguration $conf) {

                    $excluded = [ $conf->get('excluded_user') ];
                    $selected = [];

                    $stmt = $pdo->query("SELECT login FROM users WHERE active IS TRUE");

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
            'snapped' => function ($dshot) {
                $dshot->append("

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
];
