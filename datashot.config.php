<?php return [

  'snappers' => [

    'datashot' => [

      // Reference the
      'database_server' => 'workbench1',

      'database_name'   => 'datashot',

      'database_charset'   => 'utf8',
      'database_collation' => 'utf8_general_ci',

      'output_dir' => realpath(__DIR__ . '/tests/assets'),
      'output_file' => 'snapped',

      // If you want to snap the rows only
      // 'data_only' =>TRUE,

      // If you wanna dump only the ddl, triggers, functions, etc.
      // 'no_data' => TRUE,

      // Custom made property
      'excluded_users' => [ 'usr103' ],

      // The custom made cutoff property will be evaluate by the
      // return the follow closure
      'cutoff' => function (\Datashot\Core\DatabaseSnapper $snapper) {

        $stmt = $snapper->query("
          SELECT * FROM logs ORDER BY created_at DESC
          LIMIT 1
        ");

        $newest = $stmt->fetch();

        // Print message to console (<b> to bold output)
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

      // Optional generic where (bring only 2 rows per table)
      'where' => 'true order by 1 limit 2',

      'wheres' => [

        // Interpolete parameter to where clause
        'logs' => "created_at > '{cutoff}'",

        'users' => function (\Datashot\Core\DatabaseSnapper $snapper) {

          // Get parameter
          $excluded = $snapper->get('excluded_users');
          $selected = [];

          $stmt = $snapper->query("SELECT login FROM users WHERE active IS TRUE");

          foreach ($stmt->fetchAll() as $res) {
            $selected[] = $res->login;
          }

          $selected = "'" . implode("', '", $selected) . "'";
          $excluded = "'" . implode("', '", $excluded) . "'";

          return "login IN ({$selected}) and ".
                 "login NOT IN ({$excluded})";
        },
      ],

      'row_transformers' => [
        'users' => function ($row) {

          // hidding user phone numbers
          $row->phone = "+55 67 99999-1000";

          return $row;
        },

        'hash' => function ($row) {

          $row->value = "{$row->k}:value";

          return $row;
        }
      ],

      // Event-based hook
      'before' => function (\Datashot\Core\DatabaseSnapper $snapper) {

        $snapper->append("SELECT '[Before hook]...';");

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

      // Database server
      'database_server' => [
        'driver'  => 'mysql',

        'socket'  => getenv('WORKBENCH_SOCKET'),

        'host'    => getenv('WORKBENCH_HOST'),
        'port'    => getenv('WORKBENCH_PORT'),

        'username'  => getenv('WORKBENCH_USER'),
        'password'  => getenv('WORKBENCH_PASSWORD')
      ]
    ]
  ],

  'database_servers' => [
    'workbench1' => [
      'driver'  => 'mysql',

      'socket'  => getenv('WORKBENCH_SOCKET'),

      'host'    => getenv('WORKBENCH_HOST'),
      'port'    => getenv('WORKBENCH_PORT'),

      'username'  => getenv('WORKBENCH_USER'),
      'password'  => getenv('WORKBENCH_PASSWORD')
    ]
  ],

  'restoring_settings' => [
    'datashot' => [
      'workbench1' => [
        'database_name' => 'restored_datashot',

        'before' => "CREATE TABLE _before_hook (
            id integer not null auto_increment primary key,
            handle varchar(24) not null
        )",
      ]
    ],
    'datashot_sql' => [
      'workbench1' => [
        'database_name' => 'restored_datashot_sql',

        'before' => function (\Datashot\Core\SnapRestorer $restorer) {

          // You can use the restorer api to query and set values
          $value = $restorer->query("SHOW VARIABLES LIKE 'max_allowed_packet'")->fetchColumn(1);
          $restorer->set('old_packet_size', $value);

          // You can use the before hook to setup the database server
          $restorer->execute("SET GLOBAL max_allowed_packet = 1073741824");

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
    ]
  ]
];
