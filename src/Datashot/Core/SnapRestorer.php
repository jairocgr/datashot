<?php

namespace Datashot\Core;

use Datashot\Util\EventBus;

class SnapRestorer
{
    /**
     * @var EventBus
     */
    private $bus;

    /**
     * @var RestoringSettings
     */
    private $config;

    public function __construct(EventBus $bus, RestoringSettings $config)
    {
        $this->bus = $bus;
        $this->config = $config;
    }

    public function restore()
    {
        $this->bus->publish();
    }

}