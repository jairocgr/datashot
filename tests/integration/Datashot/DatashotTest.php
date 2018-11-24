<?php

namespace Datashot;

use Datashot\Lang\DataBag;
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
        $dshot = new Datashot([
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

            'output_file' => 'snapped.gz',

            'compress' => TRUE,

            // Custom made property
            'excluded_user' => 'usr103',

            // generic where bring only 2 rows per table
            'where' => 'true order by 1 limit 2',

            'wheres' => [

                'log' => "created_at > '2018-03-01'",

                'user' => function (PDO $pdo, DataBag $conf) {

                    $excluded = [ $conf->excluded_user ];
                    $selected = [];

                    $stmt = $pdo->query("SELECT login FROM active IS TRUE");

                    foreach ($stmt->fetchAll() as $res) {
                        $selected[] = $res->login;
                    }

                    return "login IN ({$selected}) and ".
                           "login NOT INT ({$excluded})";
                }
            ]
        ]);

        // to test configuration append/merge
        $dshot->config([
            'wheres' => [
                'news' => 'published IS TRUE'
            ]
        ]);

        // test where override
        $dshot->where('log', "created_at > '2018-02-01'");

        $dshot->shot();
    }

    private function restoreSnap()
    {
        $this->exec("bash {$this->ASSETS_DIR}/restore_snaped_database.sh");
    }

    private function assessRestoredSnap()
    {
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