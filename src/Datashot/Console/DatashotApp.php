<?php

namespace Datashot\Console;

use Datashot\Console\Command\SnapCommand;
use Datashot\Datashot;
use Symfony\Component\Console\Application;

class DatashotApp extends Application
{
    public function __construct()
    {
        parent::__construct('Datashot Database Snapper', Datashot::getVersion());

        $this->addCommands([
            new SnapCommand()
        ]);
    }
}