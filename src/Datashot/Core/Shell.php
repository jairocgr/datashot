<?php

namespace Datashot\Core;

use RuntimeException;
use Symfony\Component\Process\Process;

class Shell
{
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