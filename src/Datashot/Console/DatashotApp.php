<?php

namespace Datashot\Console;

use Datashot\Console\Command\ExecCommand;
use Datashot\Console\Command\ListSnapsCommand;
use Datashot\Console\Command\CopyCommand;
use Datashot\Console\Command\MkdirCommand;
use Datashot\Console\Command\RemoveSnapCommand;
use Datashot\Console\Command\ReplicateCommand;
use Datashot\Console\Command\RestoreCommand;
use Datashot\Console\Command\RunCommand;
use Datashot\Console\Command\SnapCommand;
use Datashot\Datashot;
use Symfony\Component\Console\Application;
use Dotenv\Dotenv;

class DatashotApp extends Application
{
    public function __construct()
    {
        parent::__construct('Datashot', Datashot::getVersion());

        if (file_exists(getcwd() . '/.env')) {
            // If has dotenv, tries to load the env file from the current dir
            $dotenv = Dotenv::createImmutable(getcwd());
            $dotenv->load();
        }

        $this->addCommands([
            new ExecCommand(),
            new RunCommand(),

            new ListSnapsCommand(),
            new RemoveSnapCommand(),
            new MkdirCommand(),
            new CopyCommand(),

            new SnapCommand(),
            new RestoreCommand(),
            new ReplicateCommand()
        ]);
    }
}
