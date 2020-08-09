<?php

namespace Datashot\Console\Command;

use Datashot\Core\DatabaseServer;
use Datashot\Datashot;
use Datashot\Lang\DataBag;
use Datashot\Util\ConsoleOutput;
use RuntimeException;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

abstract class BaseCommand extends Command
{
    /**
     * @var array
     */
    protected $config;

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
                'C',
                InputOption::VALUE_OPTIONAL,
                'Configuration file',
                'datashot.config.php'
             )

             ->addOption(
                'set',
                's',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Set parameter via "param=val" format'
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

        $this->datashot = new datashot($this->config);

        $this->datashot->on('output', function ($string) {
            if ($this->verbosity >= 0) {
                $this->console->puts($string);
            }
        });

        $this->setupListeners();

        $this->exec();

        return 0;
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

        $config = require $configFile;
        $config = new DataBag($config);

        foreach ($this->parseParams() as $key => $value) {
            $config->set($key, $value);
        }

        return $config->toArray();
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
            $string = substr($string, 0, $maxLenght - 3 );
            $string = rtrim($string);
            return $string . '...';
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

            if (preg_match('/[^\s]+\=.*/', $param) !== 1) {
                throw new InvalidArgumentException("Invalid parameter \"{$param}\"!");
            }

            $pieces = explode('=', $param);

            $key = trim($pieces[0]);
            $value = trim(isset($pieces[1]) ? $pieces[1] : '');

            $params[$key] = $value;
        }

        return $params;
    }

    protected function shouldProceedExecution(DatabaseServer $server)
    {
        return $server->isDevelopment()
               || $this->forcedExecution()
               || $this->confirm("Proceed execution at <b>{$server}</b> server?");
    }

    private function confirm($msg)
    {
        $msg = $this->console->parse("{$msg} (y/n)");

        if ($this->input->isInteractive()) {

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("{$msg} ", false);

            return $helper->ask($this->input, $this->output, $question);

        } else {
            // If the input is not comming from an interative terminal, then we must
            // safetly unconfirm and let the user know about it
            $this->console->writeln("{$msg} — <options=bold;fg=yellow>Canceled</>");
            return FALSE;
        }
    }

    private function forcedExecution()
    {
        if ($this->input->hasOption('force')) {
            return ($this->input->getOption("force") !== FALSE);
        } else {
            return FALSE;
        }
    }
}
