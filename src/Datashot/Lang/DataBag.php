<?php

namespace Datashot\Lang;

use ArrayAccess;
use InvalidArgumentException;
use Iterator;
use RuntimeException;

class DataBag implements ArrayAccess, Iterator
{
    protected $data;

    public function __construct($data = [])
    {
        if (empty($data) || $this->isAssociativeArray($data)) {
            $this->data = $this->parse($data);
        } else {
            throw new RuntimeException("Invalid data array!");
        }
    }

    public function merge(array $data) {
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

    public function unsetKey(...$params) {
        if (is_array($params[0])) {
            foreach ($params[0] as $key => $value) {
                $this->unsetKey($key, $value);
            }
        } else {
            $this->lookupAndUnset($params[0]);
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

        $pointer = $this->isAssociativeArray($value) ? new DataBag($value) : $value;
    }

    private function lookupAndUnset($configPath)
    {
        $keys = explode('.', $configPath);

        $pointer = &$this->data;

        foreach ($keys as $key) {
            $pointer = &$pointer[$key];
        }

        unset($pointer);
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
    public function toArray()
    {
        return $this->data;
    }

    /**
     * Get required key. Throws if key is not found.
     *
     * @retur mixed
     */
    public function getr($key, $errmsg = NULL)
    {
        if ($this->notExists($key)) {

            if (empty($errmsg)) {
                $message = "Required key \"{$key}\" not found!";
            } else {
                $message = $errmsg;
            }

            throw new RuntimeException($message);
        }

        return $this->get($key);
    }

    public function notExists($key)
    {
        return ! $this->exists($key);
    }

    public function exists($key)
    {
        try {
            $this->lookup($key);

            return TRUE;
        } catch (InvalidArgumentException $ex) {
            return FALSE;
        }
    }

    private function parse(array $data)
    {
        $keys = array_keys($data);

        foreach ($keys as $key) {

            $pointer = &$data[$key];

            if ($this->isAssociativeArray($pointer)) {
                $pointer = new DataBag($pointer);
            }
        }

        return $data;
    }

    private function isAssociativeArray($arr)
    {
        if (!is_array($arr)) {
            return false;
        }

        if ($arr === array()) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /*----------- ArrayAccess implementation --------------*/

    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->set($offset, $value);
        }
    }

    public function offsetExists($offset) {
        return $this->exists($offset);
    }

    public function offsetUnset($offset) {
        $this->unsetKey($offset);
    }

    public function offsetGet($offset) {
        return $this->getr($offset);
    }

    /*------------- Iterator implementation --------------*/
    private $position = 0;

    function rewind() {
        $this->position = 0;
    }

    function current() {
        return $this->data[$this->getKeyByIndex($this->position)];
    }

    function key() {
        return $this->getKeyByIndex($this->position);
    }

    function next() {
        $this->position++;
    }

    function valid() {

        if ($this->position >= count($this->data)) {
            return FALSE;
        }

        return TRUE;
    }

    private function getKeyByIndex($index)
    {
        return (array_keys($this->data))[$index];
    }
}
