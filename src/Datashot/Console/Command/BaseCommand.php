<?php

namespace Datashot\Console\Command;

use Datashot\Datashot;
use Datashot\Util\ConsoleOutput;
use Datashot\Util\EventBus;
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
                InputOption::VALUE_OPTIONAL,
                'Configuration file',
                'datashot.config.php'
             )

             ->addOption(
                 'set',
                 's',
                 InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                 'Set parameter'
             )

             ->addArgument(
                'snappers',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'The choosed snappers to act upon',
                [ 'default' ]
             );

        $this->config();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->config = $this->loadConfig($input);
        $this->snappers = $input->getArgument("snappers");

        $this->bus = new EventBus();

        $this->input = $input;
        $this->output = $output;

        $this->verbosity = intval(getenv('SHELL_VERBOSITY'));

        $this->console = new ConsoleOutput($input, $output);

        $this->datashot = new Datashot($this->bus, $this->config);

        $this->bus->on('output', function ($string) {
            if ($this->verbosity >= 1) {
                $this->console->puts($string);
            }
        });

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

    private function parseParams()
    {
        $params = [];

        foreach ($this->input->getOption('set') as $param) {

            if (preg_match('/[^\s]+\=[^\s]+/', $param) !== 1) {
                throw new RuntimeException("Invalid parameter \"{$param}\"!");
            }

            $pieces = explode('=', $param);

            $key = trim($pieces[0]);
            $value = trim($pieces[1]);

            $this->console->puts("{$key}: \"{$value}\"");

            $params[$key] = $value;
        }

        return $params;
    }
}