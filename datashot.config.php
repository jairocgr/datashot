<?php return [

    'snappers' => [

        'default' => [
            'triggers'  => TRUE,
            'routines'  => TRUE,

            'output_dir' => __DIR__,

            'output_file' => 'snapped',

            'compress' => TRUE,

            // generic where bring only 2 rows per table
            'where' => 'true order by 1 limit 2',
        ],

        'sun' => [
            'output_file' => 'sun',
            'database_server' => 'sun',
            'database_name' => 'sun_pdr_ro',

            'where' => true,
        ],

        'crm' => [

            'database_server' => 'workbench1',

            'database_name'   => 'datashot',

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

            'host'      => 'localhost', // getenv('WORKBENCH_HOST'),
            'port'      => 3306, // getenv('WORKBENCH_PORT'),

            'username'  => 'admin', // getenv('WORKBENCH_ADMIN'),
            'password'  => 'admin', // getenv('WORKBENCH_PASSWORD')
        ],


        'sun' => [
            'driver'    => 'mysql',

            'host'      => '127.0.0.1', // getenv('WORKBENCH_HOST'),
            'port'      => 14001, // getenv('WORKBENCH_PORT'),

            'username'  => 'root', // getenv('WORKBENCH_ADMIN'),
            'password'  => 'root', // getenv('WORKBENCH_PASSWORD')
        ]
    ],
];
