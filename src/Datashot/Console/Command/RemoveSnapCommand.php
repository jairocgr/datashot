<?php

namespace Datashot\Console\Command;

use Datashot\Core\RepositoryItem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class RemoveSnapCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('rm')
             ->setDescription('Remove a snapshot')
             ->setHelp('Remove a specific snapshot')

             ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'The snap path: repo:/sub/path-to/snapshot_*'
             )

             ->addOption(
                'dry',
                NULL,
                InputOption::VALUE_OPTIONAL,
                'Run without remove snaps',
                 FALSE
             );
    }

    protected function exec()
    {
        $path = $this->input->getArgument('path');

        $item = $this->datashot->get($path);

        $modify = $this->getActionModifier();

        $this->console->puts("Removing <b>{$item->getLocation()}</b>{$modify}...");

        if ($this->forReal()) {
            $this->rm($item);
        }

        $this->console->newLine();
    }

    private function forReal()
    {
        // For real only if not a dry-run
        return $this->input->getOption('dry') === FALSE;
    }

    private function getActionModifier()
    {
        return $this->forReal() ? '' : ' <fade>(dry-run)</fade><normal> ';
    }

    private function rm(RepositoryItem $item)
    {
        foreach ($item->ls() as $entry) {
            $this->console->fade("Removing <b>{$entry}</b><fade>...</fade>");
            $entry->rm();
        }
    }
}