<?php

namespace Datashot\Console\Command;

use Datashot\Core\DatabaseSnapper;

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

    protected function setupListeners()
    {
        $this->bus->on(DatabaseSnapper::SNAPPING, function (DatabaseSnapper $snapper) {

            if ($this->verbosity < 1) {

                $this->console->write(
                    " → Snaping <b>{$snapper->getDatabaseName()}</b> " .
                    "from <b>{$snapper->getDatabaseServer()}</b> database..."
                );

                return;
            }

            $this->console->puts(
                "Snapshoting <b>{$snapper->getDatabaseName()}</b> " .
                "from <b>{$snapper->getDatabaseServer()}</b> database "
            );

            if ($snapper->viaTcp()) {
                $this->console->puts("  host <b>{$snapper->getDatabaseHost()}:{$snapper->getDatabasePort()}</b>");
            } else {
                $this->console->puts("  socket <b>{$snapper->getDatabaseSocket()}</b>");
            }

            $this->console->puts("  user <b>{$snapper->getDatabaseUser()}</b>");
            $this->console->puts("  pwd <b>{$this->hidePwd($snapper->getDatabasePassword())}</b>");
            $this->console->newLine();
        });

        $this->bus->on(DatabaseSnapper::DUMPING_DDL, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Dump tables DDL...");
        });

        $this->bus->on(DatabaseSnapper::DUMPING_DATA, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->newLine();
        });

        $this->bus->on(DatabaseSnapper::DUMPING_VIEWS, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->newLine();
        });

        $this->bus->on(DatabaseSnapper::DUMPING_VIEW, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Dumping view <b>{$data->view}</b>...");
        });

        $this->bus->on(DatabaseSnapper::DUMPING_TABLE_DATA, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Dumping <b>{$data->table}</b>...");

            if ($this->verbosity >= 2) {
                $this->console->fade("  WHERE \"<b>{$this->cutoff($data->where, 65)}</b>\"");
            }
        });

        $this->bus->on(DatabaseSnapper::TABLE_DUMPED, function ($snapper, $data) {
            if ($this->verbosity >= 2) {

                if ($data->via_php) {

                    if ($data->rows_transformed) {
                        $type = "transformed";
                    } else {
                        $type = "unstransformed";
                    }

                    $this->console->fade("  {$data->rows} {$type} rows");
                }

                $this->console->fade("  Done in <b>{$this->format($data->time)}</b>");
                $this->console->newLine();
            }
        });

        $this->bus->on(DatabaseSnapper::DUMPING_TRIGGERS, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->newLine();
        });

        $this->bus->on(DatabaseSnapper::DUMPING_FUNCTIONS, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->newLine();
        });

        $this->bus->on(DatabaseSnapper::DUMPING_PROCEDURES, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->newLine();
        });

        $this->bus->on(DatabaseSnapper::DUMPING_TRIGGER, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Trigger <b>{$data->trigger}</b>");
        });

        $this->bus->on(DatabaseSnapper::DUMPING_FUNCTION, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Function <b>{$data->function}</b>");
        });

        $this->bus->on(DatabaseSnapper::DUMPING_PROCEDURE, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Procedure <b>{$data->procedure}</b>");
        });

        $this->bus->on(DatabaseSnapper::SNAPPED, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                $this->console->writeln(" <success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
                return;
            }

            $this->console->newLine();
            $this->console->puts("<success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
            $this->console->newLine();
        });
    }
}