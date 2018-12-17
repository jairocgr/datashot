<?php

namespace Datashot\Console\Command;

use Datashot\Core\SnapUploader;
use Symfony\Component\Console\Input\InputOption;

class UploadCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('upload')
             ->setDescription('Upload a database snapshot')
             ->setHelp('Upload a database snapshot')

             ->addOption(
                 'target', 't',
                 InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                 'Repository to restore the snaps'
             );
    }

    protected function exec()
    {
        foreach ($this->snappers as $snapper) {
            foreach ($this->getTargets() as $target) {
                $this->datashot->upload($snapper, $target);
            }
        }
    }

    private function getTargets()
    {
        return $this->input->getOption('target');
    }

    protected function setupListeners()
    {
        $this->bus->on(SnapUploader::UPLOADING, function (SnapUploader $upload) {

            $this->console->write(
                " → Uploading <b>{$upload->getSourceFileName()}</b> " .
                "to <b>{$upload->getTargetRepository()}</b> repo..."
            );

            return;
        });

        $this->bus->on(SnapUploader::DONE, function ($upload, $data) {
            $this->console->writeln(" <success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
            return;
        });
    }
}