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
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
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

    /**
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->data->database_name;
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
        return $this->data->getRequired($key);
    }

    public function hasParam($key)
    {
        return $this->data->exists($key);
    }

    public function getCollation()
    {
        return $this->data->get('database_collation', 'utf8_general_ci');
    }

    public function getCharset()
    {
        return $this->data->get('database_charset', 'utf8');
    }

    public function set($key, $value)
    {
        $this->data->set($key, $value);
    }

    public function __toString()
    {
        return $this->name;
    }
}
