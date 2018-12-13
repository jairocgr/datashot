<?php

namespace Datashot\Mysql;

use Datashot\IO\FileWriter;

class MysqlDumpFileWriter
{
    /**
     * @var FileWriter
     */
    private $writer;

    public function __construct(FileWriter $writer)
    {
        $this->writer = $writer;
    }

    public function comment($string)
    {
        return $this->writeln("-- {$string}");
    }

    public function message($msg)
    {
        return $this->command("SELECT \"{$msg}\"");
    }

    public function command($sql)
    {
        return $this->writeln("{$sql};");
    }

    public function newLine($count = 1)
    {
        return $this->write(str_repeat(PHP_EOL, $count));
    }

    public function writeln($string)
    {
        return $this->write($string . PHP_EOL);
    }

    public function write($string)
    {
        return $this->writer->write($string);
    }

    public function close()
    {
        $this->writer->close();
    }

    public function open()
    {
        $this->writer->open();
    }
}