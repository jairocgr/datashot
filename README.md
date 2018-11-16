# Datashot Database Snapper

A tool for taking partial and minified database snapshot por testing and
development propurse.

With **Datashot**, instead of taking a full database dump, you can can filter
wich rows you want to dump in order to come up with a downsized database
snapshot.

## Requirements

To install and run Datashot you must have:

 * PHP >= 5.6 with PDO extension
 * [Composer](https://getcomposer.org/) dependency manager
 * `mysqldump` on path (from mysql >= 5.6)
 * `gzip` tool on path

## Installing

Install Datashot via composer as a regular package:

    composer require jairocgr/datashot

## Usage

After requiring Datashot via composer, you can call it as a php command line
tool:

    php vendor/bin/snapper --help

To take a database snapshot, you should call:

    php vendor/bin/datashot --specs default

Datashot will look for a file `datashot.config.php` at the current directory and
search for the `default` configuration array inside it:

```php
return [

  // Datashot's configuration file named "datashot.config.php"

  // A configuration array entry
  'default' => [
    'driver'    => 'mysql', // currently mysql only

    'host' => 'localhost',
    'port' => '3306',

    // If connecting via unix domain socket
    // 'unix_socket' => '/var/run/mysqld/mysqld.sock',

    'database'  => '_my_crm_app',
    'username'  => 'root',
    'password'  => 'VOc90YngU5',

    'charset'   => 'utf8',
    'collation' => 'utf8_general_ci',

    'triggers'  => TRUE, // Dump the triggers
    'routines'  => TRUE, // Bring the procedures and functions

    // Set the directory where the snapshots will be placed, if ommited
    // the current dir will be used
    'output_dir' => 'storage/snaps/',

    // The snapshot file name, by default the database name with .gz or .sql
    // extension will be used
    'output_file' => 'my_crm_snap.gz',

    // Compress the database snapshot (via gzip)
    'compress' => TRUE,

    // WHERE clauses specification to take a partial table dump
    //
    // Datashot will dump only the rows by the given WHERE condition,
    // ommited tables will be dumped fully
    'wheres' => [
      // Dont bring deleted users
      'user' => 'deleted IS FALSE',

      // Bring the last 1000 log entries only
      'user_log' => 'true ORDER BY logid LIMIT 1000',

      // Bing the sales made in the last two months only
      'sales' => function ($pdo) {

        $now = new DateTime();
        $interval = DateInterval::createFromDateString('2 months');

        $cutoff = $now->sub($interval);

        $cutoff = $cutoff->format('Y-m-d');

        // WHERE sale_date greater than two months ago
        return "sale_date > {$cutoff}";
      }
    ]

    // Optional generic/fallback where clause for all tables
    // 'where' => 'true ORDER BY 1 LIMIT 10000'
  ]

];
```

## Restoring Snapshots

With the snapshot file in hands, you can restore it as a regular database dump:

```
gzip < storage/snaps/my_crm_snap.gz | mysql -h localhost dbname
```

## License

This project is licensed under the MIT License - see the
[LICENSE.md](LICENSE.md) file for details
