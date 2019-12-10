<?php

namespace Datashot\Console\Command;

use Datashot\Core\Shell;
use Datashot\Tests\Databases;
use Datashot\Tests\DatabasesHelper;
use Datashot\Tests\TestHelper;
use PHPUnit\Framework\TestCase;

class ExecCommandTest extends TestCase
{
    /**
     * @var Shell
     */
    private $shell;

    /**
     * @var DatabasesHelper
     */
    private $databases;

    /**
     * @var TestHelper
     */
    private $helper;

    public function setUp()
    {
        $this->helper = new TestHelper();
        $this->shell = $this->helper->getShell();
        $this->databases = $this->helper->getDatabasesHelper();
        $this->bootTestDatabase();
    }

    private function bootTestDatabase()
    {
        $this->shell->run("bash tests/assets/init-databases.sh");
    }

    public function testHealthCheck()
    {
        $this->shell->run("php bin/datashot exec db01 --at mysql56 -f --command 'SELECT 1'");
        $this->assertTrue(TRUE);
    }

    public function testInsertCommand()
    {
        $this->shell->run("
            php bin/datashot exec db01 --at mysql56 -f \
                --command \"INSERT INTO tenants (id, handle, name) VALUES (300, 'tenant300', 'Test Tenant 300 Çñ')\"
        ");

        $res = $this->databases->queryFirst('mysql56', 'db01', "SELECT * FROM tenants WHERE id = 300");

        $this->assertEquals('Test Tenant 300 Çñ', $res->name);
    }

    public function testAcrossDatabasesInsertCommand()
    {
        $this->shell->run("
            php bin/datashot exec db0* --at mysql56 -f \
                --command \"INSERT INTO tenants (id, handle, name) VALUES (500, 'tenant500', 'Test Tenant 500 Çñ')\"
        ");

        $res = $this->databases->queryFirst('mysql56', 'db01', "SELECT * FROM tenants WHERE id = 500");
        $this->assertEquals('Test Tenant 500 Çñ', $res->name);

        $res = $this->databases->queryFirst('mysql56', 'db02', "SELECT * FROM tenants WHERE id = 500");
        $this->assertEquals('Test Tenant 500 Çñ', $res->name);

        $res = $this->databases->queryFirst('mysql56', 'db03', "SELECT * FROM tenants WHERE id = 500");
        $this->assertEquals('Test Tenant 500 Çñ', $res->name);
    }

    public function testPipingFile()
    {
        $this->shell->run("
            cat tests/assets/commands.sql \
              | gzip \
              | gunzip \
              | php bin/datashot exec db01 --at mysql56 -f
        ");

        $res = $this->databases->queryFirst('mysql56', 'db01', "SELECT * FROM actions WHERE id = 102");
        $this->assertEquals('action102', $res->action);
    }

    public function testInputRedirection()
    {
        $this->shell->run("
            php bin/datashot exec db01 --at mysql56 -f \
              < tests/assets/commands.sql
        ");

        $res = $this->databases->queryFirst('mysql56', 'db01', "SELECT * FROM actions WHERE id = 102");
        $this->assertEquals('action102', $res->action);
    }

    public function testAcrossDatabasesInsertFile()
    {
        $this->shell->run("
            php bin/datashot exec db0* --at mysql56 -f \
              --script tests/assets/commands.sql
        ");

        $res = $this->databases->queryFirst('mysql56', 'db01', "SELECT * FROM actions WHERE id = 101");
        $this->assertEquals('action101', $res->action);

        $res = $this->databases->queryFirst('mysql56', 'db02', "SELECT * FROM actions WHERE id = 102");
        $this->assertEquals('action102', $res->action);

        $res = $this->databases->queryFirst('mysql56', 'db03', "SELECT * FROM actions WHERE id = 103");
        $this->assertEquals('action103', $res->action);
    }
}
