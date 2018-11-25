<?php

namespace Datashot\IO;

use RuntimeException;

class GzipFileWriter implements FileWriter
{
    private $handle;

    private $filepath;

    public function __construct($filepath)
    {
        if (!function_exists("gzopen")) {
            throw new RuntimeException(
                "Gzip lib is not installed or configured properly"
            );
        }

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
        return $this->write(str_repeat(PHP_EOL, $count));
    }

    private function fopen()
    {
        $this->mkpath($this->filepath);

        $this->handle = gzopen($this->filepath, "w");

        if ($this->handle  === FALSE) {
            throw new RuntimeException("Can not open \"{$this->filepath}\"");
        }
    }

    private function fwrite($string)
    {
        if (($bytesWritten = gzwrite($this->handle, $string)) === FALSE) {
            throw new RuntimeException("Can not write to \"{$this->filepath}\"");
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
        // no flush
    }

    private function fclose()
    {
        if (gzclose($this->handle) === FALSE) {
            throw new RuntimeException("Can not close \"{$this->filepath}\"");
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
