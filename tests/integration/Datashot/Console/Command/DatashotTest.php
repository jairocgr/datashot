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
        $this->ASSETS_DIR = realpath(__DIR__ . '/../../../../assets');
        $this->ROOT_DIR = realpath(__DIR__ . '/../../../../../');

        $dotenv = new Dotenv($this->ROOT_DIR);
        $dotenv->overload();

        $this->shell = new Shell();

        $this->application = new DatashotApp();
    }

    public function test()
    {
        $this->bootTestDatabase();
        $this->datashot();
        $this->restoreSnap();
        $this->assessRestoredSnap();
    }

    private function bootTestDatabase()
    {
        $connectionFile = $this->setupConnectionFile();

        $this->shell->run("
            mysql --defaults-file={$connectionFile} \
                < {$this->ASSETS_DIR}/datashot.sql
        ");
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

    private function datashot()
    {
        $command = $this->application->find('snap');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command'  => $command->getName(),

            // pass arguments to the helper
            'snappers' => [ 'datashot', 'datashot_sql' ],

            '--config' => "{$this->ROOT_DIR}/datashot.config.php",

            // prefix the key with two dashes when passing options,
            // e.g: '--some-option' => 'option_value',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        $this->assertContains('Done', $output);
    }

    private function restoreSnap()
    {
        $command = $this->application->find('restore');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command'  => $command->getName(),

            'snappers' => [ 'datashot', 'datashot_sql' ],

            '--config' => "{$this->ROOT_DIR}/datashot.config.php",

            '--target' => [ 'workbench1' ]
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        $this->assertContains('Done', $output);
    }

    private function assessRestoredSnap()
    {
        $this->assessDatabase('restored_datashot');
        $this->assessDatabase('restored_datashot_sql');
    }

    private function connect($database)
    {
        $socket = getenv('WORKBENCH_SOCKET');
        $host = getenv('WORKBENCH_HOST');
        $port = getenv('WORKBENCH_PORT');
        $user = getenv('WORKBENCH_USER');
        $password = getenv('WORKBENCH_PASSWORD');

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
            'hash'
        ]);

        if ($this->assertingSqlDatabase()) {

            $snapped = file_get_contents("{$this->ASSETS_DIR}/snapped.sql");

            $this->assertTableExists([
                '_test_before_hook'
            ]);

        } else {
            $this->shell->run("
                gunzip < {$this->ASSETS_DIR}/snapped.gz \
                       > {$this->ASSETS_DIR}/snapped.gunzip
            ");

            $this->assertTableExists([
                '_before_hook'
            ]);

            $snapped = file_get_contents("{$this->ASSETS_DIR}/snapped.gunzip");
        }

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
