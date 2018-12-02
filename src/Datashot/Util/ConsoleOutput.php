<?php

namespace Datashot\Util;

use Symfony\Component\Console\Style\SymfonyStyle;

class ConsoleOutput
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    public function __construct($input, $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    public function write($message = "")
    {
        $this->io->write($this->filter($message));
    }

    public function writeln($message = "")
    {
        $this->io->writeln($this->filter($message));
    }

    public function puts($message = "")
    {
        $this->io->text($this->filter($message));
    }

    public function bold(string $message)
    {
        return $this->puts("<b>{$message}</b>");
    }

    public function fade(string $message)
    {
        return $this->puts("<fade>{$message}</fade>");
    }

    public function success($message = "")
    {
        $this->puts("<success>{$message}</success>");
    }

    public function warning($message = "")
    {
        $this->puts("<warn>{$message}</warn>");
    }

    public function fail($message = "")
    {
        $this->puts("<error>{$message}</error>");
    }

    public function newLine($count = 1)
    {
        $this->io->newLine($count);
    }

    private function filter(string $message)
    {
        $message = str_replace("<b>", "<options=bold>", $message);

        $message = str_replace("<fade>", "\e[2m", $message);
        $message = str_replace("</fade>", "\e[22m", $message);

        $message = str_replace("<success>", "<bgreen>", $message);
        $message = str_replace("<good>", "<bgreen>", $message);
        $message = str_replace("<ok>", "<bgreen>", $message);

        $message = str_replace("<err>", "<bred>", $message);
        $message = str_replace("<fail>", "<bred>", $message);
        $message = str_replace("<error>", "<bred>", $message);
        $message = str_replace("<danger>", "<bred>", $message);

        $message = str_replace("<warn>", "<byellow>", $message);
        $message = str_replace("<warning>", "<byellow>", $message);

        $message = preg_replace("/\<b([a-z]+)\>/", "<options=bold;fg=$1>", $message);
        $message = preg_replace("/\<([a-z]+)\>/", "<fg=$1>", $message);

        $message = preg_replace("/\<\/[^>]*\>/", "</>", $message);

        return $message;
    }
}