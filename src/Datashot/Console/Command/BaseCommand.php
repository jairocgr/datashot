<?php

namespace Datashot\Console\Command;

use Datashot\Core\DatabaseSnapper;
use Datashot\Core\SnapperConfiguration;
use Datashot\Core\SnapRestorer;
use Datashot\Datashot;
use Datashot\Util\ConsoleOutput;
use Datashot\Util\EventBus;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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
                InputArgument::OPTIONAL,
                'Configuration file',
                'datashot.config.php'
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

            if ($snapper->viaTcp()) {
                $this->console->puts("  host <b>{$snapper->getDatabaseHost()}:{$snapper->getDatabasePort()}</b>");
            } else {
                $this->console->puts("  socket <b>{$snapper->getDatabaseSocket()}</b>");
            }

            $this->console->puts("  user <b>{$snapper->getDatabaseUser()}</b>");
            $this->console->puts("  pwd <b>{$this->formatPwd($snapper->getDatabasePassword())}</b>");
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

        $this->bus->on(DatabaseSnapper::SNAPPED, function ($snapper, $data) {

            if ($this->verbosity < 1) {
                $this->console->writeln(" <success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
                return;
            }

            $this->console->newLine();
            $this->console->puts("<success>Done ✓</success> <fade>({$this->format($data->time)})</fade>");
            $this->console->newLine();
        });

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
                $this->console->puts("  socket <b>{$db->getUnixSocket()}</b>");
            }

            $this->console->puts("  user <b>{$db->getUserName()}</b>");
            $this->console->puts("  pwd <b>{$this->formatPwd($db->getPassword())}</b>");
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

    private function formatPwd($password)
    {
        if (empty($password)) {
            return "EMPTY_PASSWORD_STRING";
        }

        $password = strval($password);

        $showedCharacters = intval(ceil(strlen($password) / 6));

        $viseblePart = substr($password, 0, $showedCharacters);

        return str_pad($viseblePart, strlen($password), '*');
    }
}