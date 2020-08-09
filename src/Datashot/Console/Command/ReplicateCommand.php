<?php

namespace Datashot\Console\Command;

use Datashot\Core\Database;
use Datashot\Core\DatabaseServer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ReplicateCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('replicate')
            ->setDescription('Replicate a database to another server')
            ->setHelp('Replicate a database to another server')

            ->addArgument(
                'databases',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'The chosen databases to be replicated'
            )

            ->addOption(
                'from',
                'm',
                InputOption::VALUE_REQUIRED,
                'Source database server'
            )

            ->addOption(
                'to',
                't',
                InputOption::VALUE_REQUIRED,
                'Target database server'
            )

            ->addOption(
                'name',
                'd',
                InputOption::VALUE_OPTIONAL,
                'The target database naming'
            );
    }

    protected function exec()
    {
        $source = $this->getSourceServer();
        $target = $this->getTargetServer();

        if ($target->isDevelopment() && $source->canReplicateTo($target)) {

            $databases = $this->getChosenDatabases();

            foreach ($source->lookupDatabases($databases) as $source) {

                $name = $this->targetDatabaseName($source);

                $this->console->puts("Replicating <b>{$source}</b> to <b>{$name}</b> at <b>{$target}</b> server...");

                $source->replicateTo($target, $name);
            }

            $this->console->newLine();

        } else {
            $this->console->warning("Replicating to {$target} is not possible/forbidden!");
            $this->console->newLine();
        }
    }

    private function getChosenDatabases()
    {
        return $this->input->getArgument('databases');
    }

    /**
     * @return DatabaseServer
     */
    private function getSourceServer()
    {
        $src = $this->input->getOption('from');

        return $this->datashot->getServer($src);
    }

    /**
     * @return DatabaseServer
     */
    private function getTargetServer()
    {
        $dst = $this->input->getOption('to');

        return $this->datashot->getServer($dst);
    }

    private function targetDatabaseName(Database $source)
    {
        $naming = $this->input->getOption('name');

        if (is_string($naming)) {
            return str_replace('{database}', $source->getName(), $naming);
        } else {
            return $source->getName();
        }
    }
}
