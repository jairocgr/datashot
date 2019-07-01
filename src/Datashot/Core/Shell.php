<?php

namespace Datashot\Core;

use RuntimeException;
use Symfony\Component\Process\Process;

class Shell
{
    /**
     * @var string
     */
    private $cwd;

    /**
     * Shell constructor.
     * @param string $cwd
     */
    public function __construct($cwd = NULL)
    {
        $this->cwd = empty($cwd) ? getcwd() : $cwd;
    }

    /**
     * @return Process
     */
    public function build($command)
    {
        $process = $this->buildProcess($command);

        $process->setTimeout(NULL);

        return $process;
    }

    /**
     * @return Process
     */
    public function sidekick($command, $input = NULL)
    {
        $process = $this->build($command);

        if ($input != NULL) {
            $process->setInput($input);
        }

        $process->start();

        return $process;
    }

    public function run($command, $input = NULL)
    {
        $process = $this->build($command);

        if ($input != NULL) {
            $process->setInput($input);
        }

        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                "Troubles executing the command! {$process->getErrorOutput()}"
            );
        }

        return $process->getOutput();
    }

    /**
     * @return Process
     */
    private function buildProcess($command)
    {
        $commandFile = tempnam(sys_get_temp_dir(), '.sh');

        register_shutdown_function(function () use ($commandFile) {
            @unlink($commandFile);
        });

        $command = trim($command);

        file_put_contents($commandFile, "{$command}");

        return new Process(['bash', $commandFile], $this->cwd);
    }
}