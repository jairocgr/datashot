<?php

namespace Datashot\Console\Command;

use Datashot\Datashot;
use Datashot\Util\ConsoleOutput;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var string[]
     */
    protected $snappers;

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
                InputOption::VALUE_OPTIONAL,
                'Configuration file',
                'datashot.config.php'
             )

             ->addArgument(
                'snappers',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'The chosen snappers to act upon',
                [ 'default' ]
             );

        $this->config();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->verbosity = intval(getenv('SHELL_VERBOSITY'));

        $this->console = new ConsoleOutput($input, $output);

        $this->config = $this->loadConfig();
        $this->snappers = $input->getArgument("snappers");

        $this->datashot = new Datashot($this->config);

        $this->datashot->on('output', function ($string) {
            if ($this->verbosity >= 1) {
                $this->console->puts($string);
            }
        });

        $this->setupListeners();

        $this->exec();
    }

    /** @return array */
    private function loadConfig()
    {
        $configFile = $this->input->getOption('config');

        if (!file_exists($configFile)) {
            throw new RuntimeException(
                "Config file \"{$configFile}\" does not exists!"
            );
        }

        return require $configFile;
    }

    protected abstract function config();

    protected abstract function exec();

    protected function format($seconds)
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

    protected function cutoff($string, $maxLenght)
    {
        if (strlen($string) > $maxLenght) {
            return substr($string, 0, $maxLenght - 3 ) . '...';
        }

        return $string;
    }

    protected function hidePwd($password)
    {
        if (empty($password)) {
            return "EMPTY_PASSWORD_STRING";
        }

        $password = strval($password);

        $showedCharacters = intval(ceil(strlen($password) / 6));

        $viseblePart = substr($password, 0, $showedCharacters);

        return str_pad($viseblePart, strlen($password), '*');
    }

    protected function setupListeners() {}
}