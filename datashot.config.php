<?php return [
  // datashot.config.php —— the datashot configuration file

  // The snappers are configurations units that stablished all the
  'snappers' => [

    'datashot' => [

      // Reference the settings defined on the 'database_servers' entry
      'database_server' => 'workbench1',

      // You could also define the database settings in a in-place fashion
      // 'database_server' => [
      //   'driver'  => 'mysql',
      //   'host'    => 'database.my-domain.com',
      //   ...
      // ],

      // Name of the database wich will be snapshoted
      'database_name'   => 'datashot',

      // https://dev.mysql.com/doc/refman/8.0/en/charset-charsets.html
      'database_charset'   => 'utf8',
      'database_collation' => 'utf8_general_ci',

      // Folder where the dump file will be placed
      'output_dir' => realpath(__DIR__ . '/tests/assets'),

      // The .gz or .sql extension will be added accordingly to the
      // outputed file name
      'output_file_name' => 'snapped',

      // All snapshot are gziped by default
      'compress' => TRUE,

      // If you want to snap the rows only
      // 'data_only' => TRUE,

      // If you wanna dump only the ddl, triggers, functions, etc.
      // 'no_data' => TRUE,

      // Custom made user-defined property
      'excluded_users' => [ 'usr103' ],

      'nrows' => 3,

      // The 'cutoff' property will be evaluate as the closure's return
      'cutoff' => function (\Datashot\Core\DatabaseSnapper $snapper) {

        // You can query the database wich is been dumped
        $stmt = $snapper->query("
          SELECT * FROM logs ORDER BY created_at DESC
          LIMIT 1
        ");

        $newest = $stmt->fetch();

        // Print the follow message to console (<b> to bold output)
        $snapper->puts("Newest log <b>{$newest->created_at}</b>");

        $newest = new DateTime($newest->created_at);
        $months = DateInterval::createFromDateString('1 months');

        $cutoff = $newest->sub($months);

        // Move de cutoff date to the first day of the month
        $cutoff = $cutoff->format("Y-m");
        $cutoff = "{$cutoff}-01 00:00:00";

        $snapper->puts("Cutoff date <b>{$cutoff}</b>");

        return $cutoff;
      },

      // Optional generic where that will be aplied to all database tables
      'where' => 'true order by 1 limit {nrows}',

      // Table-specific where used to filter the rows witch will be dumped
      'wheres' => [

        // Interpolate the 'cutoff' parameter to the where clause
        'logs' => "created_at > '{cutoff}'",
        # In this case, datashot will bring only the logs entries wich
        # created_at is newer than evalueted cutoff date (calculated by the
        # closure previously defined).

        // You can also use a closure do compute and return a where clause
        'users' => function (\Datashot\Core\DatabaseSnapper $snapper) {

          // Get user-defined parameter previously defined
          $excluded = $snapper->get('excluded_users');
          $selected = [];

          // You can query the database wich is been dumped
          $stmt = $snapper->query("SELECT login FROM users WHERE active IS TRUE");

          foreach ($stmt->fetchAll() as $res) {
            $selected[] = $res->login;
          }

          $selected = "'" . implode("', '", $selected) . "'";
          $excluded = "'" . implode("', '", $excluded) . "'";

          // Freely assemble your desired where clause
          return "login IN ({$selected}) and ".
                 "login NOT IN ({$excluded})";
        },
      ],

      // You can define closures to transform the rows that are been dumped
      // NOTICE: On big tables, this may slow things down
      'row_transformers' => [
        'users' => function ($row, \Datashot\Core\DatabaseSnapper $snapper) {

          // hidding user phone numbers
          $row->phone = '+55 67 99999-1000';

          return $row;
        },

        'hash' => function ($row) {

          $row->value = "{$row->k}:value";

          return $row;
        }
      ],

      // Event-based hook executed before the dump start
      'before' => function (\Datashot\Core\DatabaseSnapper $snapper) {

        // You can append sql commands to the snapshot file
        $snapper->append("SELECT '[Before hook]...';");

        // You can use the snapper api to get and set user-defined properties
        $users = $snapper->get('excluded_users');
        $snapper->set('excluded_users', $users);

        // The return string will be appended to the file
        return "-- [Before hook comment]\n";
      },

      'after' => function (\Datashot\Core\DatabaseSnapper $snapper) {
        $snapper->append("

          -- Set default user password
          SELECT 'Setting user password to default_pw...';
          UPDATE users SET password = sha1('default_pw');

        ");

        return "-- [After hook comment]\n";
      }
    ],

    'datashot_sql' => [
      // Inherits all settings
      'extends' => 'datashot',

      // Override inherited settings
      'compress' => FALSE,
    ]
  ],

  'database_servers' => [
    'workbench1' => [
      // For now, only mysql is supported
      'driver'  => 'mysql',

      // If socket property is setted, then it will be use instead of tcp/ip
      // host and port
      'socket'  => getenv('WORKBENCH_SOCKET'),
      # If vlucas/phpdotenv is founded, datashot will try to load the .env file
      # inside the current directory

      'host'    => getenv('WORKBENCH_HOST'),
      'port'    => getenv('WORKBENCH_PORT'),

      'username'  => getenv('WORKBENCH_USER'),
      'password'  => getenv('WORKBENCH_PASSWORD')
    ],

    'docker_mysql56' => [
      'driver'    => 'mysql',

      'socket'    => getenv('DOCKER_MYSQL56_SOCKET'),

      'host'      => getenv('DOCKER_MYSQL56_HOST'),
      'port'      => getenv('DOCKER_MYSQL56_PORT'),

      'username'  => getenv('DOCKER_MYSQL56_USER'),
      'password'  => getenv('DOCKER_MYSQL56_PASSWORD')
    ]
  ],

  // Describe how the taken snapshots can be restored to databases
  'restoring_settings' => [

    'datashot' => [

      // This entry describe how 'datashot' snapshot can be restore to the
      // 'workbench1' database server
      'workbench1' => [

        // The database name to wich the snapshot will be restored
        'database_name' => 'restored_datashot',

        // You can use before/after hook to execute scripts arround the
        // restoring process
        'before' => "CREATE TABLE _before_hook (
            id integer not null auto_increment primary key,
            handle varchar(24) not null
        )",
      ]
    ],

    'datashot_sql' => [
      'workbench1' => [
        'database_name' => 'restored_datashot_sql',

        // The before/after hooks can be define as closures too
        'before' => function (\Datashot\Core\SnapRestorer $restorer) {

          // You can use the restorer api to query and set values
          $value = $restorer->query("SHOW VARIABLES LIKE 'max_allowed_packet'")->fetchColumn(1);
          $restorer->set('old_packet_size', $value);

          // You can use the before hook to setup the database server
          $restorer->execute("SET GLOBAL max_allowed_packet = 1073741824");

          // The return string will be executed as a sql command
          return "CREATE TABLE _test_before_hook (
            id integer not null auto_increment primary key,
            handle varchar(24) not null
          )";
        },

        'after' => function (\Datashot\Core\SnapRestorer $restorer) {

          // Retrieve previous setted parameter on the before hook
          $old = $restorer->get('old_packet_size');

          // You can use the after hook to rollback settings
          $restorer->execute("SET GLOBAL max_allowed_packet = {$old}");

          $restorer->execute("UPDATE users SET password = sha1('after')");
        }
      ]
    ],
  ],

  // Repositories wich the snapshots could be uploaded to
  'repositories' => [
    'commons3' => [
      // For now, only Amazon s3 is supported
      'driver' => 's3',
      'region' => 'us-east-1',
      'bucket' => getenv('S3_BUCKET'),
      'target_folder' => 'up',
      'credentials' => [
        'key'    => getenv('S3_KEY'),
        'secret' => getenv('S3_SECRET')
      ]
    ]
  ],

  'upload_settings' => [
    'datashot' => [
      'commons3' => [
        // S3 bucket to upload to
        'bucket' => getenv('S3_BUCKET'),

        // Target folder inside de s3 bucket
        'target_folder' => 'snaps/datashot'
        # File name will be same as the snapper's output_file_name
      ]
    ],
    'datashot_sql' => [
      'commons3' => [
        // S3 bucket to upload to
        'bucket' => getenv('S3_BUCKET'),

        // Target folder inside de s3 bucket
        'target_folder' => 'snaps/datashot'
        # File name will be same as the snapper's output_file_name
      ]
    ]
  ]
];
