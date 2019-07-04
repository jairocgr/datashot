<?php

namespace Datashot\Console\Command;

use Datashot\Core\DatabaseServer;
use Datashot\Core\Snap;
use Datashot\Lang\DataBag;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class RestoreCommand extends BaseCommand
{
    const DEFAULT_CHARSET = 'utf8';

    protected function config()
    {
        $this->setName('restore')
            ->setDescription('Restore a database snapshot')
            ->setHelp('Restore a database snapshot')

            ->addArgument(
                'snaps',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'The chosen snapshots to be restored'
            )

            ->addOption(
                'to',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output database server'
            )

            ->addOption(
                'database',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Database naming'
            )

            ->addOption(
                'charset',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Database charset'
            );
    }

    protected function exec()
    {
        $target = $this->getTargetDatabase();

        if ($this->canRestoreTo($target)) {

            foreach ($this->getChoosenSnaps() as $snap) {

                $database = $this->targetDatabaseName($snap);

                $this->console->puts("Restoring <b>{$database}</b> at <b>{$target}</b> from <b>{$snap}</b> snap ...");

                if ($this->informedCharset()) {
                    $charset = $this->getChosenCharset();
                } elseif ($snap->hasCharset()) {
                    $charset = $snap->getCharset();
                } else {
                    $charset = static::DEFAULT_CHARSET;
                }

                $database = $target->createDatabase($database, new DataBag([
                    'charset' => $charset
                ]));

                $database->restore($snap);
            }

            $this->console->newLine();

        } else {
            $this->console->warning("Restoring to {$target} is forbidden!");
            $this->console->newLine();
        }
    }

    /**
     * @return DatabaseServer
     */
    private function getTargetDatabase()
    {
        $server = $this->input->getOption('to');

        return $this->datashot->getServer($server);
    }

    /**
     * @return Snap[]
     */
    private function getChoosenSnaps()
    {
        $snaps = [];

        foreach ($this->input->getArgument('snaps') as $snap) {
            $snaps = array_merge($snaps, $this->datashot->findSnaps($snap));
        }

        return $snaps;
    }

    private function targetDatabaseName(Snap $snap)
    {
        $naming = $this->input->getOption('database');

        if (is_string($naming)) {
            return str_replace('{snap}', $snap->getName(), $naming);
        } else {
            return $snap->getName();
        }
    }

    private function canRestoreTo(DatabaseServer $target)
    {
        return $target->isDevelopment();
    }

    private function getChosenCharset()
    {
        return $this->input->getOption('charset');
    }

    private function informedCharset()
    {
        return is_string($this->input->getOption('charset'));
    }
}
