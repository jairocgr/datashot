<?php

namespace Datashot\Console\Command;

use Symfony\Component\Console\Input\InputArgument;

class MkdirCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('mkdir')
             ->setDescription('Create a directory')
             ->setHelp('Create a snapshot directory')

             ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'The snap path: repo:/path/to/directory'
             );
    }

    protected function exec()
    {
        $path = $this->input->getArgument('path');

        $this->datashot->mkdir($path);
    }
}