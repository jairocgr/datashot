<?php

namespace Datashot\Console\Command;

use Datashot\Console\DatashotApp;
use Datashot\Core\Shell;
use Datashot\Datashot;
use DateTime;
use Dotenv\Dotenv;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DatashotTest extends TestCase
{
    private $ASSETS_DIR;
    private $ROOT_DIR;

    /**
     * @var Datashot
     */
    private $application;

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var string
     */
    private $currentDatabase;

    /**
     * @var Shell
     */
    private $shell;

    public function setUp()
    {
        $this->ROOT_DIR = realpath(__DIR__.'/../../../../../');

        $dotenv = new Dotenv($this->ROOT_DIR);
        $dotenv->overload();

        $this->shell = new Shell();

        $this->application = new datashotApp();
    }

    public function test()
    {
        $this->bootTestDatabase();
        $this->replicate();
        $this->datashot();
        $this->restoreSnap();
        $this->downloadSnaps();
        $this->assessRestoredSnap();
    }

    private function bootTestDatabase()
    {
        $this->shell->run("bash {$this->ROOT_DIR}/tests/assets/init-databases.sh");

        // $connectionFile = $this->setupConnectionFile();

        // $this->shell->run("
        //    mysql --defaults-file={$connectionFile} \
        //        < {$this->ASSETS_DIR}/datashot.sql
        // ");
    }

    private function setupConnectionFile()
    {
        $socket = getenv('WORKBENCH_SOCKET');
        $host = getenv('WORKBENCH_HOST');
        $port = getenv('WORKBENCH_PORT');
        $user = getenv('WORKBENCH_USER');
        $password = getenv('WORKBENCH_PASSWORD');

        $connectionFile = $commandFile = tempnam(sys_get_temp_dir(), '.test');

        register_shutdown_function(function () use ($connectionFile) {
            @unlink($connectionFile);
        });

        if (empty($socket)) {
            $connectionParams = "host={$host}\n" .
                                "port={$port}\n";
        } else {
            $connectionParams = "socket={$socket}\n";
        }

        file_put_contents($connectionFile,
            "[client]\n" .
            $connectionParams .
            "user={$user}\n" .
            "password={$password}\n"
        );

        return $connectionFile;
    }

    private function replicate()
    {
        $command = $this->application->get('replicate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command'  => $command->getName(),

            'databases' => [ 'db03' ],

            '--config' => "{$this->ROOT_DIR}/datashot.config.php",

            '--from' => 'mysql56',

            '--to' => 'mysql57'

            // prefix the key with two dashes when passing options,
            // e.g: '--some-option' => 'option_value',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        $this->assertContains('Replicating', $output);

        $this->checkReplicatedDatabase('db03');
    }

    private function checkReplicatedDatabase($database)
    {
        $this->connect($database);

        $this->assertTableExists([
            'tenants',
            'users',
            'logs',
            'news',
            'hash'
        ]);

        $this->assertTableRows([
            'tenants' => 4,
            'users' => 11,
            'logs' => 5,
            'news' => 4,
            'hash' => 4
        ]);

        $this->assertViewExists([ 'user_log' ]);

        $this->assertFunctionExists([
            'hello', 'hi'
        ]);

        $this->assertProcedureExists([ 'user_count' ]);
    }

    private function datashot()
    {
        $command = $this->application->get('snap');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command'  => $command->getName(),

            'databases' => [ 'db03', 'db01' ],

            '--config' => "{$this->ROOT_DIR}/datashot.config.php",

            // Overriding snapper configuration in time
            '--set' => [ 'snappers.quick.nrows=2' ],

            '--from' => 'mysql56',

            '--snapper' => 'quick',

            '--to' => 'mirror'

            // prefix the key with two dashes when passing options,
            // e.g: '--some-option' => 'option_value',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        $this->assertContains('Done', $output);
    }

    private function restoreSnap()
    {
        $command = $this->application->get('restore');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command'  => $command->getName(),

            'snaps' => [ 'mirror:/db0*.gz' ],

            '--config' => "{$this->ROOT_DIR}/datashot.config.php",

            '--to' => 'mysql57',

            '--database' => 'replica_{snap}'
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        $this->assertContains('Restoring', $output);
    }

    private function assessRestoredSnap()
    {
        $this->assessDatabase('replica_db03');
        $this->assessDatabase('replica_db01');
    }

    private function downloadSnaps()
    {
        $this->downloadSnap('db01');
        $this->downloadSnap('db03');
    }

    private function downloadSnap($database)
    {
        $command = $this->application->get('cp');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command'  => $command->getName(),

            'src' => "mirror:/{$database}",

            'dst' => "local:replica_{$database}",

            '--config' => "{$this->ROOT_DIR}/datashot.config.php",
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        $this->assertContains("Transfering {$database}", $output);
    }

    private function connect($database)
    {
        $socket = getenv('MYSQL57_SOCKET');
        $host = getenv('MYSQL57_HOST');
        $port = getenv('MYSQL57_PORT');
        $user = getenv('MYSQL57_USER');
        $password = getenv('MYSQL57_PASSWORD');

        if (empty($socket)) {
            $dsn = "mysql:host={$host};" .
                   "port={$port};" .
                   "dbname={$database}";
        } else {
            $dsn = "mysql:unix_socket={$socket};" .
                   "dbname={$database}";
        }

        $this->pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ]);

        $this->currentDatabase = $database;
    }

    private function assertTableExists($tables)
    {
        foreach ($tables as $table) {
            $this->assertNotFalse(
                $this->pdo->query("SELECT 1 FROM `{$table}` LIMIT 1")
            );
        }
    }

    private function assertViewExists($views)
    {
        foreach ($views as $view) {
            $this->assertNotFalse(
                $this->pdo->query("SELECT 1 FROM `{$view}` LIMIT 1")
            );
        }
    }

    private function assertFunctionExists($functions)
    {
        foreach ($functions as $function) {
            $this->assertNotFalse(
                $this->pdo->query("SHOW CREATE FUNCTION `{$function}`;")
            );
        }
    }

    private function assertProcedureExists($procedures)
    {
        foreach ($procedures as $procedure) {
            $this->assertNotFalse(
                $this->pdo->query("SHOW CREATE PROCEDURE `{$procedure}`;")
            );
        }
    }

    private function assertTableRows($tables)
    {
        foreach ($tables as $table => $rows) {
            $this->assertEquals(
                $rows,
                intval(
                    $this->pdo->query("SELECT COUNT(*) FROM `{$table}`")
                        ->fetchColumn(0)
                )
            );
        }
    }

    private function assessDatabase($database)
    {
        $this->connect($database);

        $this->assertTableExists([
            'tenants',
            'users',
            'logs',
            'news',
            'hash',
            '_before_hook'
        ]);

        // if ($this->assertingSqlDatabase()) {
        //
        //     $snapped = file_get_contents("{$this->ASSETS_DIR}/snapped.sql");
        //
        //     $this->assertTableExists([
        //         '_test_before_hook'
        //     ]);
        //
        // } else {

        // }

        $snapped = $this->getSqlSnapshot($database);


        $this->assertContains("'default_pw'", $snapped);
        $this->assertContains('[Before hook]', $snapped);
        $this->assertContains('[Before hook comment]', $snapped);
        $this->assertContains('[After hook comment]', $snapped);

        $this->assertTableRows([
            'tenants' => 2,
            'users' => 6,
            'logs' => 3,
            'news' => 2,
            'hash' => 2
        ]);

        $this->assertViewExists([ 'user_log' ]);

        $this->assertFunctionExists([
            'hello', 'hi'
        ]);

        $this->assertProcedureExists([ 'user_count' ]);

        $this->assertUsers();

        $this->assertLogs();

        $this->assertHash();
    }

    private function getSqlSnapshot($database)
    {
        $this->shell->run("
            gunzip < {$this->ROOT_DIR}/snaps/{$database}.gz \
                   > {$this->ROOT_DIR}/snaps/{$database}.sql \
        ");

        return file_get_contents("{$this->ROOT_DIR}/snaps/$database.sql");
    }

    private function assertingSqlDatabase()
    {
        return strpos($this->currentDatabase, '_sql') !== FALSE;
    }

    private function assertUsers()
    {
        $rows = $this->pdo->query('SELECT phone, active, password FROM users');

        $this->assertTrue($rows->rowCount() > 0);

        foreach ($rows as $user) {
            $this->assertEquals(TRUE, $user->active);

            if ($this->assertingSqlDatabase()) {
                $this->assertEquals(sha1('after'), $user->password);
            } else {
                $this->assertEquals(sha1('default_pw'), $user->password);
            }

            $this->assertEquals("+55 67 99999-1000", $user->phone);
        }
    }

    private function assertLogs()
    {
        $rows = $this->pdo->query('SELECT * FROM logs');

        $this->assertEquals(3, $rows->rowCount());

        foreach ($rows as $log) {
            $date = new DateTime($log->created_at);

            $this->assertGreaterThanOrEqual(2018, $date->format('Y'));
            $this->assertGreaterThanOrEqual(3, $date->format('m'));
        }
    }

    private function assertHash()
    {
        $rows = $this->pdo->query('SELECT * FROM hash');

        $this->assertEquals(2, $rows->rowCount());

        $hash = $rows->fetchAll();

        $this->assertEquals('key101', $hash[0]->k);
        $this->assertEquals('key101:value', $hash[0]->value);

        $this->assertEquals('key102', $hash[1]->k);
        $this->assertEquals('key102:value', $hash[1]->value);
    }
}
