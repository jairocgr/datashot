<?php

namespace Datashot\Console\Command;

use Datashot\Core\DatabaseServer;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ExecCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('exec')
             ->setDescription('Execute commands inside a database')
             ->setHelp('Execute commands inside a database')

             ->addArgument(
                'databases',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The chosen databases to act upon'
             )

             ->addOption(
                'at',
                NULL,
                InputOption::VALUE_REQUIRED,
                'Database server'
             )

             ->addOption(
                'command',
                'c',
                InputOption::VALUE_OPTIONAL,
                'The sql command to be executed'
             )

             ->addOption(
                'script',
                't',
                InputOption::VALUE_OPTIONAL,
                'The script file to be executed'
             )

             ->addOption(
                'force',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Force execution when production server',
                FALSE
             );
    }

    protected function exec()
    {
        $server = $this->getChosenServer();

        // Production server must be guarded from acidental execution
        if ($this->shouldProceedExecution($server)) {

            $command = $this->getCommand();

            if ($this->databaseWasInformed()) {
                $databases = $this->getChosenDatabases();

                foreach ($server->lookupDatabases($databases) as $database) {
                    $this->console->puts("Executing in <b>{$database}</b> at <b>{$server}</b> server...");
                    $database->run($command);
                }
            } else {
                // Run with no particular database being selected
                $this->console->puts("Executing in <b>{$server}</b> server...");
                $server->run($command);
            }

            $this->console->newLine();
        }
    }

    /**
     * @return string[]
     */
    private function getChosenDatabases()
    {
        return $this->input->getArgument('databases');
    }

    /**
     * @return DatabaseServer
     */
    private function getChosenServer()
    {
        $server = $this->input->getOption('at');

        return $this->datashot->getServer($server);
    }

    private function getCommand()
    {
        if ($this->input->getOption('script') !== NULL) {
            return $this->input->getOption("script");
        }

        if ($this->input->getOption("command") !== NULL) {
            return $this->input->getOption("command");
        }

        if ($this->input->isInteractive()) {
            // If neither script or command has been informed, and the STDIN
            // is coming from an interactive terminal, them we know that no
            // database command has ben informed
            throw new InvalidArgumentException(
                "You must pass at least one database command to be executed"
            );
        }

        // Neither script or command has been informed, then we assume that
        // the commands are being written into STDIN via pipe or i/o redirect
        return "php://stdin";
    }

    private function databaseWasInformed()
    {
        return ! empty($this->input->getArgument('databases'));
    }
}
