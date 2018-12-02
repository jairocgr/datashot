<?php

namespace Datashot\Core;

use Datashot\IO\FileWriter;
use Datashot\IO\GzipFileWriter;
use Datashot\IO\TextFileWriter;

use Datashot\Lang\DataBag;

class SnapperConfiguration
{
    /**
     * @var string
     */
    private $name;

    /** @var DataBag */
    private $data;

    /**
     * @var FileWriter
     */
    private $outputFile;

    public function __construct($name, array $data)
    {
        $this->name = $name;
        $this->data = new DataBag($data);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return FileWriter
     */
    public function getOutputFile()
    {
        if ($this->outputFile == NULL) {

            $filepath = $this->getOutputFilePath();

            if ($this->compressOutput()) {
                $writer = new GzipFileWriter($filepath);
            } else {
                $writer = new TextFileWriter($filepath);
            }

            return $writer;
        }

        return $this->outputFile;
    }

    /**
     * @return string
     */
    public function getOutputFilePath()
    {
        return $this->data->get('output_dir', getcwd()) . DIRECTORY_SEPARATOR .
               $this->data->get('output_file', $this->name) . '.' .
               $this->getOutputExtension();
    }

    /**
     * @return bool
     */
    public function compressOutput()
    {
        return $this->data->get('compress', true);
    }

    /**
     * @return bool
     */
    public function dumpAll()
    {
        return ! $this->data->get('data_only', false);
    }

    /**
     * @return bool
     */
    public function dumpData()
    {
        return ! $this->data->get('no_data', false);
    }

    private function getOutputExtension()
    {
        return ($this->compressOutput() ? 'gz' : 'sql');
    }

    public function hasRowTransformer($table)
    {
        $transformers = $this->data->get('row_transformers');

        return isset($transformers[$table]);
    }

    public function getRowTransformer($table)
    {
        return $this->data->get("row_transformers.{$table}");
    }

    public function getDriver()
    {
        return $this->getDatabaseServer()->getDriver();
    }

    public function getHost()
    {
        return $this->getDatabaseServer()->getHost();
    }

    public function getPort()
    {
        return $this->getDatabaseServer()->getPort();
    }

    public function getPassword()
    {
        return $this->getDatabaseServer()->getPassword();
    }

    public function getUser()
    {
        return $this->getDatabaseServer()->getUserName();
    }

    /**
     * @return string
     */
    public function getDatabase()
    {
        return $this->data->database_name;
    }

    /**
     * @return DatabaseServer
     */
    public function getDatabaseServer()
    {
        return $this->data->database_server;
    }

    /**
     * @return bool
     */
    public function hasClosure($key)
    {
        return $this->data->exists($key) && is_callable($this->data->get($key));
    }

    public function get($key, $defaultValue = NULL)
    {
        return $this->data->get($key, $defaultValue);
    }
}