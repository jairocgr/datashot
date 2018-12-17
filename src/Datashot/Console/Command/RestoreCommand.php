<?php

namespace Datashot\Console\Command;

use Datashot\Core\SnapRestorer;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

class RestoreCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('restore')
             ->setDescription('Restore a database snapshot')
             ->setHelp('Restore a database snapshot')

             ->addOption(
                 'target', 't',
                 InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
                 'Database to restore the snaps'
             );
    }

    protected function exec()
    {
        foreach ($this->snappers as $snapper) {
            foreach ($this->getTargets() as $target) {
                $this->datashot->restore($snapper, $target);
            }
        }
    }

    private function getTargets()
    {
        return $this->input->getOption('target');
    }

    protected function setupListeners()
    {
        $this->bus->on(SnapRestorer::RESTORING, function (SnapRestorer $restorer) {

            if ($this->verbosity < 1) {

                $this->console->write(
                    " → Restoring <b>{$restorer->getSourceFileName()}</b> " .
                    "to <b>{$restorer->getTargetDatabase()}</b> database..."
                );

                return;
            }

            $db = $restorer->getTargetDatabase();

            $this->console->puts(
                "Restoring <b>{$restorer->getSourceFileName()}</b> " .
                "to <b>{$restorer->getTargetDatabase()}</b> database "
            );

            if ($db->viaTcp()) {
                $this->console->puts("  host <b>{$db->getHost()}:{$db->getPort()}</b>");
            } else {
                $this->console->puts("  socket <b>{$db->getSocket()}</b>");
            }

            $this->console->puts("  user <b>{$db->getUserName()}</b>");
            $this->console->puts("  pwd <b>{$this->hidePwd($db->getPassword())}</b>");
            $this->console->newLine();
        });

        $this->bus->on(SnapRestorer::RESTORED, function ($restorer, $data) {

            if ($this->verbosity < 1) {
                $this->console->writeln(" <success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
                return;
            }

            $this->console->write('</>');

            $this->console->newLine();
            $this->console->puts("<success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
            $this->console->newLine();
        });

        $this->bus->on(SnapRestorer::CREATING_DATABASE, function (SnapRestorer $restorer) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Creating <b>{$restorer->getTargetDatabaseName()}</b> at <b>{$restorer->getTargetDatabase()}</b>...");

            if ($this->verbosity >= 2) {
                $this->console->fade("  charset <b>{$restorer->getDatabaseCharset()} ({$restorer->getDatabaseCollation()})</b>");
            }

            $this->console->newLine();
        });

        $this->bus->on(SnapRestorer::STDOUT, function (SnapRestorer $restorer, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            static $first = TRUE;

            if ($first) {
                $this->console->puts("Executing <b>{$restorer->getSourceFileName()}</b>:");
                $this->console->write("  ");
                $first = FALSE;
            }

            $tag = ($data->type == Process::ERR) ? '<red>' : '</>';

            $this->console->write(str_replace("\n", "\n  ", $tag . $data->data));
        });
    }
}