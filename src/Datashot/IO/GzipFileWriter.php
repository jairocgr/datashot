<?php

namespace Datashot\IO;

use RuntimeException;

class GzipFileWriter implements FileWriter
{
    private $handle;

    private $filepath;

    private $firstOpening;

    public function __construct($filepath)
    {
        if (!function_exists("gzopen")) {
            throw new RuntimeException(
                "Gzip lib is not installed or configured properly"
            );
        }

        $this->filepath = $filepath;
        $this->firstOpening = TRUE;
    }

    public function write($string)
    {
        if ($this->handle == NULL) {
            $this->open();
        }

        return $this->fwrite($string);
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

    public function open()
    {
        $this->mkpath($this->filepath);

        if ($this->firstOpening) {
            // If first opening truncate the file and place the pointer
            // at the begining
            $this->handle = gzopen($this->filepath, "w9");
        } else {
            // Place the pointer at the end for appending writings
            $this->handle = gzopen($this->filepath, "a");
        }

        stream_set_write_buffer($this->handle, 4096 * 4);

        if ($this->handle  === FALSE) {
            throw new RuntimeException("Can not open \"{$this->filepath}\"");
        }

        $this->firstOpening = FALSE;
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
        fflush($this->handle);
    }

    private function fclose()
    {
        if (gzclose($this->handle) === FALSE) {
            throw new RuntimeException("Can not close \"{$this->filepath}\"");
        }

        $this->handle = NULL;
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
