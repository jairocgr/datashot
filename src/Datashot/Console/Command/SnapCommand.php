<?php

namespace Datashot\Console\Command;

use Datashot\Core\DatabaseServer;
use Datashot\Core\DatabaseSnapper;
use Datashot\Core\SnapLocation;
use Datashot\Core\SnapperConfiguration;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SnapCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('snap')
             ->setDescription('Take a database snapshot')
             ->setHelp('Take a database snapshot')

             ->addArgument(
                'databases',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'The chosen databases to be snapped'
             )

             ->addOption(
                'from',
                'm',
                InputOption::VALUE_REQUIRED,
                'Database server'
             )

             ->addOption(
                'snapper',
                'p',
                InputOption::VALUE_REQUIRED,
                'Choosed snapper profile'
             )

             ->addOption(
                'to',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Output location'
             );
    }

    protected function exec()
    {
        $source    = $this->getChosenServer();
        $config    = $this->getSnapperConfiguration();
        $databases = $this->getChosenDatabases();
        $output    = $this->getOutput();

        foreach ($source->lookupDatabases($databases) as $database) {
            $database->snap($config, $output);
        }
    }

    protected function setupListeners()
    {
        $this->datashot->on(DatabaseSnapper::SNAPPING, function (DatabaseSnapper $snapper) {

            $this->console->puts(
                "Snapshoting <b>{$snapper->getSnappedDatabase()}</b> " .
                "from <b>{$snapper->getSnappedServer()}</b> database..."
            );

            if ($this->verbosity >= 1) {
                $this->console->newLine();
            }
        });

        $this->datashot->on(DatabaseSnapper::DUMPING_DDL, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Dump tables DDL...");
        });

        $this->datashot->on(DatabaseSnapper::DUMPING_DATA, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->newLine();
        });

        $this->datashot->on(DatabaseSnapper::DUMPING_VIEWS, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->newLine();
        });

        $this->datashot->on(DatabaseSnapper::DUMPING_VIEW, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Dumping view <b>{$data->view}</b>...");
        });

        $this->datashot->on(DatabaseSnapper::DUMPING_TABLE_DATA, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Dumping <b>{$data->table}</b>...");

            if ($this->verbosity >= 2) {
                $this->console->fade("  WHERE \"<b>{$this->cutoff($data->where, 65)}</b>\"");
            }
        });

        $this->datashot->on(DatabaseSnapper::TABLE_DUMPED, function ($snapper, $data) {
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

        $this->datashot->on(DatabaseSnapper::DUMPING_TRIGGERS, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->newLine();
        });

        $this->datashot->on(DatabaseSnapper::DUMPING_FUNCTIONS, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->newLine();
        });

        $this->datashot->on(DatabaseSnapper::DUMPING_PROCEDURES, function () {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->newLine();
        });

        $this->datashot->on(DatabaseSnapper::DUMPING_TRIGGER, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Trigger <b>{$data->trigger}</b>");
        });

        $this->datashot->on(DatabaseSnapper::DUMPING_FUNCTION, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Function <b>{$data->function}</b>");
        });

        $this->datashot->on(DatabaseSnapper::DUMPING_PROCEDURE, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                return;
            }

            $this->console->puts("Procedure <b>{$data->procedure}</b>");
        });

        $this->datashot->on(DatabaseSnapper::SNAPPED, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                $this->console->writeln(" <success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
                $this->console->newLine();
                return;
            }

            $this->console->newLine();
            $this->console->puts("<success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
            $this->console->newLine();
        });
    }

    private function getChosenDatabases()
    {
        return $this->input->getArgument('databases');
    }

    /**
     * @return DatabaseServer
     */
    private function getChosenServer()
    {
        $server = $this->input->getOption('from');

        return $this->datashot->getServer($server);
    }

    /**
     * @return SnapperConfiguration
     */
    private function getSnapperConfiguration()
    {
        $snapper = $this->input->getOption("snapper");

        if (empty($snapper)) {
            return $this->datashot->getSnapperConfiguration();
        } else {
            return $this->datashot->getSnapperConfiguration($snapper);
        }
    }

    /**
     * @return SnapLocation
     */
    private function getOutput()
    {
        $out = $this->input->getOption("to");
        return $this->datashot->parse($out);
    }
}