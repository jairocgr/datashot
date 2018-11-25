<?php

namespace Datashot\Lang;

use InvalidArgumentException;

trait DataBag
{
    protected $data;

    public function __construct($data = [])
    {
        $this->data = $data;
    }

    public function append(array $data) {
        $this->data = array_replace_recursive($this->data, $data);
    }

    public function get($key, $defaultValue = NULL) {
        try {
            return $this->lookup($key);
        } catch (InvalidArgumentException $ex) {
            return $defaultValue;
        }
    }

    public function set(...$params) {
        if (is_array($params[0])) {
            foreach ($params[0] as $key => $value) {
                $this->set($key, $value);
            }
        } else {
            $this->lookupAndSet($params[0], $params[1]);
        }
    }

    private function lookup($configPath)
    {
        $keys = explode('.', $configPath);

        $pointer = &$this->data;

        foreach ($keys as $key) {

            if (!isset($pointer[$key])) {
                throw new InvalidArgumentException(
                    "Config \"{$configPath}\" does not exists! "
                );
            }

            $pointer = &$pointer[$key];
        }

        return $pointer;
    }

    private function lookupAndSet($configPath, $value)
    {
        $keys = explode('.', $configPath);

        $pointer = &$this->data;

        foreach ($keys as $key) {
            $pointer = &$pointer[$key];
        }

        $pointer = $value;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * Return internal wrapped data
     *
     * return array
     */
    public function getData()
    {
        return $this->data;
    }
}
