<?php

namespace Datashot\Console\Command;

class SnapCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('snap')
             ->setDescription('Take a database snapshot')
             ->setHelp('Take a database snapshot');
    }

    protected function exec()
    {
        foreach ($this->snappers as $snapper) {
            $this->datashot->snap($snapper);
        }
    }
}