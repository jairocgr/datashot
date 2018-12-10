<?php

namespace Datashot\Console;

use Datashot\Console\Command\RestoreCommand;
use Datashot\Console\Command\SnapCommand;
use Datashot\Datashot;
use Symfony\Component\Console\Application;

class DatashotApp extends Application
{
    public function __construct()
    {
        parent::__construct('Datashot Database Snapper', Datashot::getVersion());

        if (class_exists("\Dotenv\Dotenv")) {
            // If has dotenv, tries to load the env file from the current dir
            $dotenv = new \Dotenv\Dotenv(getcwd());
            $dotenv->safeLoad();
        }

        $this->addCommands([
            new SnapCommand(),
            new RestoreCommand()
        ]);
    }
}