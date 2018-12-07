<?php

namespace Datashot\Console\Command;

use Symfony\Component\Console\Input\InputArgument;

class RestoreCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('restore')
             ->setDescription('Restore a database snapshot')
             ->setHelp('Restore a database snapshot')

             ->addOption(
                 'target', 't',
                 InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                 'Database to restore the snaps'
             );
    }

    protected function exec()
    {
        foreach ($this->snappers as $snapper) {
            foreach ($this->input->getOption('target') as $target) {
                $this->datashot->restore($snapper, $target);
            }
        }
    }
}