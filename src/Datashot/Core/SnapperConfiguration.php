<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;

class SnapperConfiguration
{
    /** @var string */
    private $name;

    /** @var DataBag */
    private $data;

    public function __construct($name, DataBag $data)
    {
        $this->name = $name;
        $this->data = $data;

        $this->validate();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getOutputFilePath()
    {
        return $this->data->get('output_dir', getcwd()) . DIRECTORY_SEPARATOR .
               $this->getOutputFileName();
    }

    /**
     * @return string
     */
    public function getOutputFileName()
    {
        return $this->data->get('output_file_name', $this->name) .
              ($this->compressOutput() ? '.gz' : '.sql');
    }

    /**
     * @return bool
     */
    public function compressOutput()
    {
        return $this->data->get('compress', TRUE);
    }

    /**
     * @return bool
     */
    public function dumpAll()
    {
        return ! $this->data->get('data_only', FALSE);
    }

    /**
     * @return bool
     */
    public function dumpData()
    {
        return ! $this->data->get('no_data', FALSE);
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
    public function getDatabaseName()
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

    public function getr($key)
    {
        return $this->data->getr($key);
    }

    public function hasParam($key)
    {
        return $this->data->exists($key);
    }

    public function viaTcp()
    {
        return $this->getDatabaseServer()->viaTcp();
    }

    public function getSocket()
    {
        return $this->getDatabaseServer()->getSocket();
    }

    public function getDatabasePassword()
    {
        return $this->getDatabaseServer()->getPassword();
    }

    public function getCollation()
    {
        return $this->data->get('database_collation', 'utf8_general_ci');
    }

    public function getCharset()
    {
        return $this->data->get('database_charset', 'utf8');
    }

    private function validate()
    {
        if ($this->data->exists('output_dir')) {
            $this->data->checkIfString('output_dir', "Invalid :key :value for {$this->name} snapper!");
        }

        if ($this->data->exists('output_file_name')) {
            $this->data->checkIfString('output_file_name', "Invalid :key :value for {$this->name} snapper!");
            $this->data->checkIfNotEmptyString('output_file_name', ":key can not be empty in {$this->name} snapper!");
        }

        if ($this->data->exists('database_name')) {
            $this->data->checkIfString('database_name', "Invalid :key :value for {$this->name} snapper!");
            $this->data->checkIfNotEmptyString('database_name', ":key can not be empty in {$this->name} snapper!");
        }
    }

    public function set($key, $value)
    {
        $this->data->set($key, $value);
    }
}