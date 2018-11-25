<?php

namespace Datashot;

use Datashot\Mysql\MysqlDatabaseSnapper;
use Datashot\Mysql\MysqlDumperConfig;
use PDO;
use PHPUnit\Framework\TestCase;

class DatashotTest extends TestCase
{
    private $ASSETS_DIR;

    public function setUp()
    {
        $this->ASSETS_DIR = realpath(__DIR__ . '/../../assets');
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
        $this->exec("bash {$this->ASSETS_DIR}/restore_test_database.sh");
    }

    private function datashot()
    {
        $dshot = new MysqlDatabaseSnapper([
            'driver' => 'mysql',

            'host' => 'localhost',
            'port' => '3306',

            'database'  => 'datashot',
            'username'  => 'admin',
            'password'  => 'admin',

            'charset'   => 'utf8',
            'collation' => 'utf8_general_ci',

            'triggers'  => TRUE,
            'routines'  => TRUE,

            'output_dir' => $this->ASSETS_DIR,

            'output_file' => 'snapped',

            'compress' => TRUE,

            // Custom made property
            'excluded_user' => 'usr103',

            // generic where bring only 2 rows per table
            'where' => 'true order by 1 limit 2',

            'wheres' => [

                'log' => "created_at > '2018-03-01'",

                'users' => function (PDO $pdo, MysqlDumperConfig $conf) {

                    $excluded = [ $conf->excluded_user ];
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
            MysqlDatabaseSnapper::SNAPED => function ($dshot) {
                $dshot->append("

                  -- Set default user password
                  SELECT 'Setting user password to default_pw...';
                  UPDATE users SET password = sha1('default_pw');

                ");
            }
        ]);

        // to test configuration append/merge
        $dshot->config([
            'wheres' => [
                'news' => 'published IS TRUE'
            ]
        ]);

        $dshot->on(MysqlDatabaseSnapper::SNAPED, function ($dshot) {
            $dshot->append("
                -- Second append
                SELECT 'Test second event-based hook...';
            ");
        });

        // test where override
        $dshot->where('log', "created_at > '2018-02-01'");

        $dshot->snap();

        // Test plain text dump
        $dshot->config([
            'output_file' => 'snapped.plain',

            'compress' => FALSE
        ]);

        $dshot->snap();
    }

    private function restoreSnap()
    {
        $this->exec("bash {$this->ASSETS_DIR}/restore_snaped_database.sh");
    }

    private function assessRestoredSnap()
    {
        $this->assertFileEquals(
            "{$this->ASSETS_DIR}/snapped.plain.sql",
            "{$this->ASSETS_DIR}/snapped.expanded.sql"
        );

        $this->assertTrue(TRUE);
    }

    private function exec($command)
    {
        $output = [];
        $return = 0;

        exec($command, $output, $return);

        echo "\n";
        foreach ($output as $line) {
            echo "  {$line}\n";
        }
        echo "\n";
    }
}
