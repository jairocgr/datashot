<?php

namespace Datashot\Console\Command;

use Datashot\Core\DatabaseSnapper;
use Datashot\Datashot;
use Datashot\Util\ConsoleOutput;
use Datashot\Util\EventBus;
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
     * @var SymfonyStyle
     */
    protected $io;

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

    protected function configure()
    {
        $this->addOption(
                'conf',
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

        $this->io = new SymfonyStyle($input, $output);

        $this->console = new ConsoleOutput($input, $output);

        $this->datashot = new Datashot($this->bus, $this->config);

        $this->setupListeners();

        $this->exec();
    }

    /** @return array */
    private function loadConfig(InputInterface $input)
    {
        $configFile = $input->getOption('conf');

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
        $this->bus->on(DatabaseSnapper::DUMPING_SCHEMA, function () {
            $this->console->puts("Dumping database schema...");
        });

        $this->bus->on(DatabaseSnapper::DUMPING_TABLE_DDL, function ($snapper, $data) {
            $this->console->puts("CREATE TABLE <b>{$data->table}</b>");
        });

        $this->bus->on(DatabaseSnapper::DUMPING_TABLE_DATA, function ($snapper, $data) {
            $this->console->puts("Dumping table data <b>{$data->table}</b>...");
            $this->console->fade("  WHERE \"<b>{$this->cutoff($data->where, 65)}</b>\"");
        });

        $this->bus->on(DatabaseSnapper::TABLE_DUMPED, function ($snapper, $data) {
            $this->console->fade("  Done in <b>{$this->format($data->time)}</b>");
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