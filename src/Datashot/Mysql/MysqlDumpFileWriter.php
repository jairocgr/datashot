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
        return $this->writer->writeln("-- {$string}");
    }

    public function message($msg)
    {
        return $this->command("SELECT \"{$msg}\"");
    }

    public function command($sql)
    {
        return $this->writer->writeln("{$sql};");
    }

    public function newLine($count = 1)
    {
        return $this->writer->newLine($count);
    }

    public function writeln($string)
    {
        return $this->writer->writeln($string);
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