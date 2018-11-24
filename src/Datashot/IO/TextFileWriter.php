<?php

namespace Datashot\IO;

use RuntimeException;

class TextFileWriter implements FileWriter
{
    private $handle;

    private $filepath;

    public function __construct($filepath)
    {
        $this->filepath = $filepath;
    }

    public function write($string)
    {
        if ($this->handle == NULL) {
            $this->fopen();
        }

        return $this->fwrite($string);
    }

    public function writeln($string)
    {
        return $this->write($string . PHP_EOL);
    }

    public function flush()
    {
        $this->checkIfOpened();

        $this->fflush();
    }

    public function close()
    {
        $this->checkIfOpened();

        $this->fflush();

        $this->fclose();
    }

    public function newLine($count = 1)
    {
        $this->write(str_repeat(PHP_EOL, $count));
    }

    private function fopen()
    {
        $this->mkpath($this->filepath);

        $this->handle  = fopen($this->filepath, "w");

        if ($this->handle  === FALSE) {
            throw new RuntimeException("Could not open \"{$this->filepath}\"");
        }
    }

    private function fwrite($string)
    {
        if (($bytesWritten = fwrite($this->handle, $string)) === FALSE) {
            throw new RuntimeException("Could not write to \"{$this->filepath}\"");
        }

        return $bytesWritten;
    }

    private function checkIfOpened()
    {
        if ($this->handle == NULL) {
            throw new RuntimeException("Unopened file \"{$this->filepath}\"");
        }
    }

    private function fflush()
    {
        if (fflush($this->handle) === FALSE) {
            throw new RuntimeException("Could not write to \"{$this->filepath}\"");
        }
    }

    private function fclose()
    {
        if (fclose($this->handle) === FALSE) {
            throw new RuntimeException("Could not close \"{$this->filepath}\"");
        }
    }

    private function mkpath($filepath)
    {
        $path = pathinfo($filepath, PATHINFO_DIRNAME);

        if (!is_dir($path)) {
            if (mkdir($path, 0777, true) === FALSE) {
                throw new RuntimeException(
                    "Can not create directory \"{$path}\""
                );
            }
        }
    }
}
