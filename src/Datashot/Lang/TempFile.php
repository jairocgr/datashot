<?php


namespace Datashot\Lang;

use RuntimeException;

class TempFile
{
    /**
     * @var string
     */
    private $path;

    /**
     * Return the temporary file path
     *
     * @return string
     */
    public function getPath()
    {
        if ($this->path == NULL) {
            $this->path = $this->genTmpFile();
        }

        return $this->path;
    }

    public function __toString()
    {
        return $this->getPath();
    }

    private function genTmpFile()
    {
        $path = tempnam(sys_get_temp_dir(), ".tmp.");

        if ($path === FALSE) {
            throw new RuntimeException("Could not create temporary file!");
        }

        return $path;
    }

    public function writeln($line)
    {
        $this->write("{$line}\n");
    }

    public function write($string)
    {
        if (file_put_contents($this->getPath(), strval($string), FILE_APPEND) === FALSE) {
            throw new RuntimeException(
                "Could not write to \"{$this}\" " .
                "temporary file!"
            );
        }
    }

    public function sink($resource)
    {
        $myself = $this->fopen($this->getPath(), "w+");

        if (stream_copy_to_stream($resource, $myself) === FALSE) {
            throw new RuntimeException("Could copy resource to temporary file!");
        }

        fclose($myself);
    }

    private function fopen($filepath, $mode)
    {
        if ($handle = fopen($filepath, $mode)) {
            return $handle;
        } else {
            throw new RuntimeException("File \"{$filepath}\" could not be opened!");
        }
    }
}
