<?php return [

  'database_servers' => [
    'mysql56' => [
      'driver'   => 'mysql',
      'socket'   => env('MYSQL56_SOCKET', ''),
      'host'     => env('MYSQL56_HOST', 'localhost'),
      'port'     => env('MYSQL56_PORT', 3306),

      'user'     => env('MYSQL56_USER', 'root'),
      'password' => env('MYSQL56_PASSWORD', 'root'),

      // If you mark as a 'production' server, a confirmation yes or no
      // question will be pront in every execution and no drop action will
      // be performed
      'production' => TRUE
    ],

    'mysql57' => [
      'driver'   => 'mysql',
      'socket'   => env('MYSQL57_SOCKET', ''),
      'host'     => env('MYSQL57_HOST', 'localhost'),
      'port'     => env('MYSQL57_PORT', 3306),

      'user'     => env('MYSQL57_USER', 'root'),
      'password' => env('MYSQL57_PASSWORD', 'root')
    ],

    'mysql80' => [
      'driver'   => 'mysql',
      'socket'   => env('MYSQL80_SOCKET', ''),
      'host'     => env('MYSQL80_HOST', 'localhost'),
      'port'     => env('MYSQL80_PORT', 3306),

      'user'     => env('MYSQL80_USER', 'root'),
      'password' => env('MYSQL80_PASSWORD', 'root')
    ],

    'local' => [
      'driver'   => 'mysql',
      'socket'   => env('MYSQL_LOCAL_SOCKET', ''),
      'host'     => env('MYSQL_LOCAL_HOST', 'localhost'),
      'port'     => env('MYSQL_LOCAL_PORT', 3306),

      'user'     => env('MYSQL_LOCAL_USER', 'root'),
      'password' => env('MYSQL_LOCAL_PASSWORD', 'root')
    ],
  ],

  'snappers' => [
    'quick' => [

      // Folder where the dump file will be placed
      'output_dir' => realpath(__DIR__ . '/tests/assets'),

      // The outputed file name (extension .gz or .sql  will be added
      // accordingly to the 'compress' settings)
      'output_file_name' => 'snapped',

      // All snapshot are gziped by default
      'compress' => TRUE,

      // If you want to snap the rows only
      // 'data_only' => TRUE,

      // If you wanna dump only the ddl, triggers, functions, etc.
      // 'no_data' => TRUE,

      // Optional generic where that will be aplied to all database tables
      'where' => 'true order by 1 limit {nrows}',

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

        $snapper->append("
          CREATE TABLE _before_hook (
            id integer not null auto_increment primary key,
            handle varchar(24) not null );
        ");

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
    ]
  ],

  'repositories' => [
    'local' => [
      'driver' => 'fs',
      'path' => __DIR__ . '/snaps'
    ],

    'remote' => [
      'driver'     => 's3',
      'bucket'     => env('S3_BUCKET'),
      'region'     => env('S3_REGION'),
      // 'profile'    => 'remote',
      'access_key' => env('S3_ACESS_KEY'),
      'secret_key' => env('S3_SECRET_ACESS_KEY'),
      'base_path'  => 'snaps'
    ],

    'mirror' => [
      'driver'      => 'sftp',
      'host'        => env('MIRROR_HOST'),
      'port'        => 22,
      'user'        => 'ec2-user',
      'password'    => '',
      'private_key' => env('MIRROR_PEM'),
      'root'        => '/var/www/html/files'
    ]
  ],

  // User define task
  'create-user' => function (\Datashot\Datashot $wrangler) {

    $MIN_PRIVILEGES = 'EVENT, EXECUTE, SELECT, SHOW DATABASES, SHOW VIEW';

    $server   = $wrangler->arg('server');
    $user     = $wrangler->arg('user');
    $passwd   = $wrangler->arg('password');
    $profile  = $wrangler->arg('profile');

    if ($profile == 'admin') {
      $privileges = 'ALL PRIVILEGES, GRANT OPTION, PROXY';
    } elseif ($profile == 'writer') {
      $privileges = $MIN_PRIVILEGES . ', DELETE, INSERT, LOCK TABLES, UPDATE';
    } elseif ($profile == 'reader') {
      $privileges = $MIN_PRIVILEGES;
    } else {
      throw new RuntimeException("Invalid user profile \"{$profile}\"!");
    }

    $server = $wrangler->getServer($server);

    if ($server->query("SELECT * FROM mysql.user WHERE user = '{$user}'")->rowCount() > 0) {
        // User already exists, so lets re-create him
        $server->exec("DROP USER {$user}");
    }

    $wrangler->puts("Creating user <b>{$user}</b> at <b>{$server}</b> server...");
    $wrangler->puts("");

    $server->exec("CREATE USER '{$user}'@'%' IDENTIFIED BY '{$passwd}'");
    $server->exec("GRANT {$privileges} ON *.* TO '{$user}'");
  },

];
