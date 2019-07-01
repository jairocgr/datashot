<?php

namespace Datashot\Console\Command;

use Datashot\Core\RepositoryItem;
use Datashot\Core\Snap;
use Symfony\Component\Console\Input\InputArgument;

class ListSnapsCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('ls')
             ->setDescription('List all the snapshots')
             ->setHelp('List all the snapshots inside a repository')

             ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'The repository path: repo:/sub/path-to',
                ''
             );
    }

    protected function exec()
    {
        $path = $this->input->getArgument('path');

        $item = $this->datashot->get($path);

        $this->prints($item);
    }

    private function prints(RepositoryItem $item)
    {
        $entries = $item->ls();

        if (empty($entries)) {
            $this->console->puts("No snapshots to list");
            $this->console->puts("");
        }

        else {
            foreach ($entries as $entry) {
                $this->printItem($entry);
            }

            $this->console->newLine();
        }
    }

    private function printItem(RepositoryItem $item)
    {
        if ($item->isSnapshot())
        {
            $out = $this->formatLine($item);
            $this->console->puts($out);
        }

        if ($item->isDirectory())
        {
            $out = "<byellow>{$item}</byellow> <fade>dir</fade>";
            $this->console->puts($out);
        }
    }

    private function formatLine(Snap $snap)
    {
        $size = $snap->getHumanSize();
        $date = $snap->getCanonicalDate();

        return "<b>{$snap}</b> <fade>{$size} — {$date}</fade>";
    }
}