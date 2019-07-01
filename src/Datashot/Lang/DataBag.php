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

    public function merge($data) {
        if (is_array($data)) {
            $this->data = array_replace_recursive($this->data, $data);
        } elseif ($data instanceof DataBag) {
            $this->merge($data->toArray());
        } else {
            throw new RuntimeException('Could not merge "'.gettype($data).'" data!');
        }
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
            foreach ($params[0] as $param) {
                $this->unsetKey($param);
            }
        } else {
            $this->lookupAndSet($params[0], NULL);
        }
    }

    private function lookup($configPath)
    {
        if (empty($configPath)) {
            throw new RuntimeException("Invalid empty key!");
        }

        $keys = explode('.', $configPath);

        $pointer = $this->data;

        foreach ($keys as $key) {

            if (!isset($pointer[$key])) {
                throw new InvalidArgumentException(
                    "Config \"{$configPath}\" does not exists! "
                );
            }

            $pointer = $pointer[$key];
        }

        if (is_string($pointer)) {
            return $this->resolveBoundedParams($pointer);
        }

        return $pointer;
    }

    private function lookupAndSet($configPath, $value)
    {
        if (empty($configPath)) {
            throw new RuntimeException("Invalid empty key!");
        }

        $keys = explode('.', $configPath);

        $lastKey = $keys[count($keys) - 1];

        unset($keys[count($keys) - 1]);

        $pointer = &$this->data;

        foreach ($keys as $key) {
            $pointer = &$pointer[$key];
        }

        $pointer[$lastKey] = $this->isAssociativeArray($value) ? new DataBag($value) : $value;
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
        return $this->getRequired($offset);
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


    public function is($key, $expectedType)
    {
        $value = $this->get($key);

        $type = gettype($value);

        if ($type == 'object') {

            if ($value instanceof $expectedType) {
                return TRUE;
            } else {
                return FALSE;
            }

        } elseif ($type != $expectedType) {
            return FALSE;
        }

        return TRUE;
    }

    private function resolveBoundedParams($string)
    {
        foreach ($this->extractBoundedParams($string) as $param) {

            $value = $this->get($param, NULL);

            if (is_scalar($value)) {
                $string = str_replace("{{$param}}", $value, $string);
            }
        }

        return $string;
    }

    private function extractBoundedParams($string)
    {
        $matches = [];
        $params = [];

        preg_match_all("/\{[^\}]+\}/", $string, $matches);

        foreach ($matches[0] as $match) {
            $match = str_replace('{', '', $match);
            $match = str_replace('}', '', $match);

            $params[] = $match;
        }

        return $params;
    }

    public function extract($key, ...$params)
    {
        if (count($params) > 1) {
            $defaultValue = $params[0];
            $extractor = $params[1];
        } elseif (count($params) == 1) {
            $defaultValue = NULL;
            $extractor = $params[0];
        }

        return call_user_func($extractor, $this->get($key, $defaultValue), Asserter::getInstance());
    }

    public function getRequired($key)
    {
        if ($this->exists($key)) {
            return $this->get($key);
        } else {
            throw new RuntimeException(
                "Required key \"{$key}\" not found!"
            );
        }
    }
}
