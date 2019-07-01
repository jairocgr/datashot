<?php

namespace Datashot\Console\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class RunCommand extends BaseCommand
{
    protected function config()
    {
        $this->setName('run')
             ->setDescription('Run a user-defined task')
             ->setHelp('Execute a user-defined task')

             ->addArgument(
                'task',
                InputArgument::REQUIRED,
                'The chosen task to be executed'
             )

             ->addOption(
                'arg',
                'a',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Task argument via "param=val" format'
             );
    }

    protected function exec()
    {
        $task = $this->getChosenTask();
        $args = $this->getArgs();

        $this->datashot->run($task, $args);
    }

    private function getArgs()
    {
        $args = [];

        foreach ($this->input->getOption('arg') as $arg) {

            if (preg_match('/[^\s]+\=.*/', $arg) !== 1) {
                throw new InvalidArgumentException("Invalid argument \"{$arg}\"!");
            }

            $pieces = explode('=', $arg);

            $key = trim($pieces[0]);
            $value = trim(isset($pieces[1]) ? $pieces[1] : '');

            $args[$key] = $value;
        }

        return $args;
    }

    private function getChosenTask()
    {
        return $this->input->getArgument("task");
    }
}