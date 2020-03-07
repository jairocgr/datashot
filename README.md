# Datashot Database Snapper

A tool for taking partial and minified database snapshots for testing and
development purpose.

Instead of taking a full database dump, you can filter which rows you want to
dump in order to come up with a downsized database snapshot.

## Requirements

To install and run **datashot** you must have:

 * PHP >= 5.6 with PDO extension
 * `zlib` PHP extension for gzip compression
 * MySQL client (`mysql` and `mysqldump` on path)
 * Gzip on path
 * [Composer](https://getcomposer.org/) dependency manager

## Installing

Install it as a regular package via composer:

    composer require jairocgr/datashot

## Usage

You can call it as a command line tool:

    php vendor/bin/datashot --help

## Getting Started

With `datashot` you can filter which rows you want to dump to come up with a
much smaller database dump.

You can reduce ginormous multi-gigabyte databases in to a small gziped file
ready to be restored in to staging and local develoment environments.

This kind of power come up handy for troubleshooting production bugs and all
arround better development experience with real life data that best reflects
your application usage than a mocked or seeded schema.

With `datashot` you can for instance take a database dump with only the orders
from the current quarter.

You can also perform other operations like:

 * Fully `replicate` databases from a host to another,
 * `restore` existant dumps to a database hosts, and
 * upload/download dumps from a SFTP or S3 repositories via `cp` command.

### Configuration

The first step is to setup the `datashot.config.php` file in the root of your
application repository.

This is the configuration file where you set the database hosts, passwords and
the _SQL_ `WHERE` clauses in order to slice the database down.

> For a more complete and commented and configuration file see the sample
> `datashot.config.php` file inside this repository root directory

#### Database Hosts

You have to configure all your database in order to be able to work with it.

```php
return [

  'database_servers' => [

    // A database server called 'live1'
    'live1' => [
      // Only mysql for now, maby postgres in the future
      'driver'   => 'mysql',

      // The env function will read from the environment or .env file
      'socket'   => env('MYSQL56_SOCKET', ''),
      'host'     => env('MYSQL56_HOST', 'localhost'),
      'port'     => env('MYSQL56_PORT', 3306),

      'user'     => env('MYSQL56_USER', 'root'),
      'password' => env('MYSQL56_PASSWORD', 'root'),

      // If you mark it as a 'production' server, a confirmation question
      // will be pronted in every execution and no drop action will
      // be performed for safety reasons
      'production' => TRUE
    ]
  ]
];
```

#### Repositories

You have to set the repositories where you will store the dumps.

```php
return [

  'repositories' => [
    'local' => [
      'driver' => 'fs',
      'path' => __DIR__ . '/snaps' // Local snaps directory
    ],

    'remote' => [
      'driver'     => 's3',
      'bucket'     => env('S3_BUCKET'),
      'region'     => env('S3_REGION'),
      // 'profile'    => 'remote',
      'access_key' => env('S3_ACESS_KEY'),
      'secret_key' => env('S3_SECRET_ACESS_KEY'),
      'base_path'  => 'snaps' // Remote path will be like s3://bucket-name/snaps
    ],
  ],

];
```

#### Snappers

The snappers tell `datashot` how to slice down the database.

```php
return [

  'snappers' => [
     'quick' => [

       // If you want to snap the rows only
       // 'data_only' => TRUE,

       // If you wanna dump only the ddl, triggers, functions, etc.
       // 'no_data' => TRUE,

       // Custom made user-defined property for later interpolation
       'cutoff' => '(NOW() - INTERVAL 3 MONTH)',

       // Table-specific where used to filter the rows witch will be dumped
       'wheres' => [

         // Interpolate the 'cutoff' parameter in the where clause for the
         // "logs" table
         'logs' => "created_at > '{cutoff}'",

         // Bring only the active users
         'users' => 'active = TRUE',
       ],

     ]
  ]

];
```

### Your First Snapshot

To take a database snapshot using the previously configured file:

    php vendor/bin/datashot snap myerp --from live1 --to remote:quick_snap --snapper quick

Then `datashot` will take a proper `mysqldump` from the scheme `myerp` that
is running inside the production server `live1` and it will be using the `quick`
snapper to cut the `logs` and `users` table down.

Then it will upload a file called `quick.gz` to the remote s3 repository called
`remote` previously configured in the `datashot.config.php` configuration
file.

## Restoring Snapshots

You can use `datashot` to download and `restore` your snapshots:

    php vendor/bin/datashot restore remote:quick --to dev --database myerp_dev

The command above will download the `quick` snapshot previously taken, restore
the dump as `myerp_dev` schema at `dev` databaser server.

You can also restore the snapshot like a regular gziped _SQL_ dump file:

    gunzip < path/to/quick.gz | mysql -h localhost myerp

## Hat Tipping

I tip my hat to [ifsnop/mysqldump-php](https://github.com/ifsnop/mysqldump-php)
for providing insights on how to dump a mysql database via PHP/PDO.

## License

This project is licensed under the MIT License - see the
[LICENSE](LICENSE) file for details
