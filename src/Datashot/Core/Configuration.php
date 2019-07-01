<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;
use Datashot\Mysql\MysqlDatabaseServer;
use InvalidArgumentException;
use RuntimeException;

class Configuration
{
    /**
     * @var DataBag
     */
    private $data;

    /**
     * @var EventBus
     */
    private $bus;


}