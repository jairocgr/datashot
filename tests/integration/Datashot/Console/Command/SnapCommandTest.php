<?php

namespace Datashot\Console\Command;

use Datashot\Console\DatashotApp;
use Datashot\Mysql\MysqlDumperConfig;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SnapCommandTest extends TestCase
{
    private $ASSETS_DIR;
    private $ROOT_DIR;

    public function setUp()
    {
        $this->ASSETS_DIR = realpath(__DIR__ . '/../../../../assets');
        $this->ROOT_DIR = realpath(__DIR__ . '/../../../../../');

        $dotenv = new Dotenv($this->ROOT_DIR);
        $dotenv->overload();
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
        $this->exec("/bin/bash {$this->ASSETS_DIR}/restore_test_database.sh");
    }

    private function datashot()
    {
        $application = new DatashotApp();

        $command = $application->find('snap');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),

            // pass arguments to the helper
            'snappers' => [ 'crm', 'crm_sql' ],

            '--config' => "{$this->ROOT_DIR}/datashot.config.php",

            // prefix the key with two dashes when passing options,
            // e.g: '--some-option' => 'option_value',
        ));

        // the output of the command in the console
        $output = $commandTester->getDisplay();

        $this->assertContains('Done', $output);
    }

    private function restoreSnap()
    {
        $this->exec("/bin/bash {$this->ASSETS_DIR}/restore_snaped_database.sh");
    }

    private function assessRestoredSnap()
    {
        $this->assertFileEquals(
            "{$this->ASSETS_DIR}/snapped.expanded.sql",
            "{$this->ASSETS_DIR}/snapped.sql"
        );
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
