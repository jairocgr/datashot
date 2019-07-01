<?php

namespace Datashot\Lang;

trait DataWrapper
{
    /** @var DataBag */
    private $data;

    public function get($key, $defaultValue = NULL)
    {
        return $this->data->get($key, $defaultValue);
    }

    public function set($key, $value)
    {
        $this->data->set($key, $value);
    }

    public function exists($key)
    {
        return $this->data->exists($key);
    }
}