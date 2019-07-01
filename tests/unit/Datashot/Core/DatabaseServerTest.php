<?php


namespace Datashot\Core;

use Datashot\Lang\DataBag;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class DatabaseServerTest extends TestCase
{
    /**
     * @var DataBag
     */
    private $BASE_CONFIG;

    public function setUp()
    {
        $this->BASE_CONFIG = new DataBag([
            'driver'   => 'mysql',
            'socket'   => '/var/run/mysqld/mysqld.sock',
            'host'     => 'database.host.br',
            'port'     => '3306',
            'user' => 'admin',
            'password' => 'w4ZyKRasEaqQcQ'
        ]);
    }


    public function test()
    {
        $db = new DatabaseServer("main-server", $this->BASE_CONFIG);

        $this->assertEquals(DatabaseServer::MYSQL, $db->getDriver());

        $this->assertSame('/var/run/mysqld/mysqld.sock', $db->getSocket());
        $this->assertSame('database.host.br', $db->getHost());
        $this->assertSame(3306, $db->getPort());

        $this->assertSame('admin', $db->getUser());
        $this->assertSame('w4ZyKRasEaqQcQ', $db->getPassword());
    }

    public function testEmptyHost()
    {
        $this->expectException(InvalidArgumentException::class);

        $config = $this->BASE_CONFIG;
        $config->unsetKey(['socket', 'host']);

        new DatabaseServer("main-server", $config);
    }

    public function testInvalidDriver()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp("/.* driver .*/");

        $config = $this->BASE_CONFIG;
        $config->set('driver', 'red');

        new DatabaseServer("main-server", $config);
    }

    public function testInvalidFloatPort()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp("/.* port .*/");

        $config = $this->BASE_CONFIG;
        $config->set('port', 2324.32);

        new DatabaseServer("main-server", $config);
    }

    public function testInvalidStringPort()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp("/.* port .*/");

        $config = $this->BASE_CONFIG;
        $config->set('port', "2324A");

        new DatabaseServer("main-server", $config);
    }
}