<?php

namespace Datashot\Console\Command;

use Datashot\Core\DatabaseSnapper;
use Datashot\Datashot;
use Datashot\Util\ConsoleOutput;
use Datashot\Util\EventBus;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand extends Command
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $snapper;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Datashot
     */
    protected $datashot;

    /**
     * @var EventBus
     */
    protected $bus;

    /**
     * @var ConsoleOutput
     */
    protected $console;

    /**
     * @var int
     */
    protected $verbosity;

    protected function configure()
    {
        $this->addOption(
                'config',
                'c',
                InputArgument::OPTIONAL,
                'Configuration file',
                'datashot.config.php'
             )

             ->addArgument(
                'snapper',
                InputArgument::OPTIONAL,
                'The choose snapper configuration',
                'default'
             );

        $this->config();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->config = $this->loadConfig($input);
        $this->snapper = $input->getArgument("snapper");

        $this->bus = new EventBus();

        $this->input = $input;
        $this->output = $output;

        $this->verbosity = intval(getenv('SHELL_VERBOSITY'));

        $this->console = new ConsoleOutput($input, $output);

        $this->datashot = new Datashot($this->bus, $this->config);

        $this->setupListeners();

        $this->exec();
    }

    /** @return array */
    private function loadConfig(InputInterface $input)
    {
        $configFile = $input->getOption('config');

        if (!file_exists($configFile)) {
            throw new RuntimeException(
                "Config file \"{$configFile}\" does not exists!"
            );
        }

        return require $configFile;
    }

    protected abstract function config();

    protected abstract function exec();

    private function setupListeners()
    {
        $this->bus->on(DatabaseSnapper::SNAPPING, function (DatabaseSnapper $snapper, $data) {

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

            $this->console->puts("  host <b>{$snapper->getDatabaseHost()}:{$snapper->getDatabasePort()}</b>");
            $this->console->puts("  user <b>{$snapper->getDatabaseUser()}</b>");
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

        $this->bus->on(DatabaseSnapper::SNAPED, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                $this->console->writeln(" <success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
                return;
            }

            $this->console->newLine();
            $this->console->puts("<success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
            $this->console->newLine();
        });
    }

    private function format($seconds)
    {
        if ($seconds < 1) {
            return number_format($seconds * 1000, 2) . 'ms';
        }

        $seconds = intval(ceil($seconds));

        $secondsInAMinute = 60;
        $secondsInAnHour = 60 * $secondsInAMinute;
        $secondsInADay = 24 * $secondsInAnHour;

        // Extract days
        $days = floor($seconds / $secondsInADay);

        // Extract hours
        $hourSeconds = $seconds % $secondsInADay;
        $hours = floor($hourSeconds / $secondsInAnHour);

        // Extract minutes
        $minuteSeconds = $hourSeconds % $secondsInAnHour;
        $minutes = floor($minuteSeconds / $secondsInAMinute);

        // Extract the remaining seconds
        $remainingSeconds = $minuteSeconds % $secondsInAMinute;
        $seconds = ceil($remainingSeconds);

        // Format and return
        $timeParts = [];
        $sections = [
            'day' => (int)$days,
            'h'   => (int)$hours,
            'min' => (int)$minutes,
            'sec' => (int)$seconds,
        ];

        foreach ($sections as $name => $value) {
            if ($value > 0) {
                $timeParts[] = $value . $name . ($value == 1 ? '' : 's');
            }
        }

        return implode(', ', $timeParts);
    }

    private function cutoff($string, $maxLenght)
    {
        if (strlen($string) > $maxLenght) {
            return substr($string, 0, $maxLenght - 3 ) . '...';
        }

        return $string;
    }
}