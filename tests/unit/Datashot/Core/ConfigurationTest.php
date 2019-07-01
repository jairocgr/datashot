<?php

namespace Datashot\Core;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    /**
     * @var Configuration
     */
    private $config;

    protected function setUp()
    {
        $ROOT_DIR = realpath(__DIR__ . '/../../../../');
        $CONFIG_FILE = "{$ROOT_DIR}/datashot.config.php";

        $dotenv = new Dotenv($ROOT_DIR);
        $dotenv->overload();

        $this->config = new Configuration(include $CONFIG_FILE);
    }

    public function testSnapper()
    {
        $snapper = $this->config->getSnapper('datashot');

        $this->assertSame('datashot', $snapper->getName());
        $this->assertSame(3306, $snapper->getPort());
        $this->assertSame(
            realpath(__DIR__ . '/../../../assets') . '/snapped.gz',
            $snapper->getOutputFilePath()
        );
    }

    public function testSnapper2()
    {
        $snapper = $this->config->getSnapper('datashot_sql');

        $this->assertSame(
            realpath(__DIR__ . '/../../../assets') . '/snapped.sql',
            $snapper->getOutputFilePath()
        );
    }
}