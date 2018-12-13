<?php

namespace Datashot\Util;

use RuntimeException;
use Symfony\Component\Process\Process;

class Shell
{
    private static $instance;

    /**
     * @return Shell
     */
    public static function getInstance()
    {
        if (static::$instance == NULL) {
            static::$instance = new Shell();
        }

        return self::$instance;
    }

    private function __construct() {}

    /**
     * @return Process
     */
    public function sidekick($command)
    {
        $process = $this->buildProcess($command);

        $process->setTimeout(NULL);

        $process->start();

        return $process;
    }

    public function run($command)
    {
        $process = $this->buildProcess($command);

        $process->setTimeout(NULL);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                "Troubles executing the command! {$process->getErrorOutput()}"
            );
        }
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

        return new Process(['bash', $commandFile]);
    }
}