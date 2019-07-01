<?php

namespace Datashot\Console\Command;

use Datashot\Core\RepositoryItem;
use Datashot\Core\SnapLocation;
use Symfony\Component\Console\Input\InputArgument;

class CopyCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('cp')
             ->setDescription('Copy a snapshot')
             ->setHelp('Copy a specific snapshot')

             ->addArgument(
                'src',
                InputArgument::REQUIRED,
                'The snap path: repo:/sub/path-to/snapshot_*'
             )

             ->addArgument(
                'dst',
                InputArgument::OPTIONAL,
                'The snap path: repo:/sub/path-to/snapshot_*',
                 ''
             );
    }

    protected function exec()
    {
        $src = $this->input->getArgument('src');
        $dst = $this->input->getArgument('dst');

        $src = $this->datashot->get($src);
        $dst = $this->datashot->parse($dst);

        $this->prints($src, $dst);

        foreach ($src->ls() as $entry) {
            $this->copy($entry, $dst);
        }

        $this->console->newLine();
    }

    private function prints(RepositoryItem $src, SnapLocation $dst)
    {
        $this->console->puts("Coping <b>{$src->getLocation()}</b> to <b>{$dst}</b>...");
    }

    private function copy(RepositoryItem $item, SnapLocation $dst)
    {
        $this->console->fade("Transfering <b>{$item}</b><fade>...</fade>");
        $item->copyTo($dst);
    }
}