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
            foreach ($params[0] as $key => $value) {
                $this->unsetKey($key, $value);
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

    public function isString($key)
    {
        return is_string($this->get($key));
    }

    public function required($key, $errmsg = "Key \":key\" not found!")
    {
        if ($this->notExists($key)) {
            throw new RuntimeException($this->interpolate($errmsg, [
                'key' => $key
            ]));
        }
    }

    public function regex($key, $regex, $errmsg = "Invalid value :value for key :key")
    {
        $value = $this->get($key);

        if (!preg_match($regex, $value)) {
            throw new RuntimeException($this->interpolate($errmsg, [
                'key' => $key,
                'value' => $this->valueToString($value)
            ]));
        }
    }

    private function interpolate($msg, array $params)
    {
        foreach ($params as $key => $value) {
            $msg = str_replace(":{$key}", $value, $msg);
        }

        return $msg;
    }

    private function valueToString($value)
    {
        if (null === $value) {
            return 'NULL';
        }

        if (true === $value) {
            return 'TRUE';
        }

        if (false === $value) {
            return 'FALSE';
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return get_class($value).': '.self::valueToString($value->__toString());
            }

            return get_class($value);
        }

        if (is_resource($value)) {
            return 'resource';
        }

        if (is_string($value)) {
            return '"'.$value.'"';
        }

        return strval($value);
    }

    public function between($key, $minValue, $maxValue, $errmsg = "Invalid value :value for key :key")
    {
        $value = $this->getInt($key);

        if ($value > $maxValue || $value < $minValue) {
            throw new RuntimeException($this->interpolate($errmsg, [
                'key' => $key,
                'value' => $this->valueToString($this->get($key))
            ]));
        }
    }

    public function getInt($key, $defaultValue = NULL)
    {
        return intval($this->get($key, $defaultValue));
    }

    public function oneOf($key, array $values, $errmsg = "Invalid value :value for key :key")
    {
        $value = $this->get($key);

        if (!in_array($value, $values, true)) {
            throw new RuntimeException($this->interpolate($errmsg, [
                'key' => $key,
                'value' => $this->valueToString($value)
            ]));
        }
    }

    public function checkIfString($key, $errmsg = "Invalid :value for key :key! Must be a string")
    {
        $value = $this->get($key);

        if (!is_string($value)) {
            throw new RuntimeException($this->interpolate($errmsg, [
                'key' => $key,
                'value' => $this->valueToString($value)
            ]));
        }
    }

    public function checkIfNotEmptyString($key, $errmsg = "Key :key must not be empty!")
    {
        $value = $this->get($key);

        if (empty(trim(strval($value)))) {
            throw new RuntimeException($this->interpolate($errmsg, [
                'key' => $key
            ]));
        }
    }

    public function checkIfIsACollectionOf($key, $expectedType)
    {
        $values = $this->get($key);

        foreach ($values as $index => $value) {

            $type = gettype($value);

            if ($type == 'object') {

                if ($value instanceof $expectedType) {
                    // ok
                } else {
                    $class = get_class($value);

                    throw new RuntimeException(
                        "{$key}[{$index}] must a instance of {$expectedType}, " .
                        "\"{$class}\" instead."
                    );
                }

            } elseif ($type != $expectedType) {
                throw new RuntimeException(
                    "{$key}[{$index}] must a {$expectedType}, {$type} instead!"
                );
            }
        }
    }

    public function checkIfIs($key, $expectedType, $errmsg = ":key must by a :expected_type, :given_type instead!")
    {
        if (!$this->is($key, $expectedType)) {
            throw new RuntimeException($this->interpolate($errmsg, [
                'key' => $key,
                'expected_type' => $expectedType,
                'given_type' => $this->valueToString($this->get($key))
            ]));
        }
    }

    public function checkIfNotEmpty($key, $errmsg = ":key must not be empty!")
    {
        $value = $this->get($key, NULL);

        if (empty($value)) {
            throw new RuntimeException($this->interpolate($errmsg, [
                'key' => $key,
                'value' => $this->valueToString($value)
            ]));
        }
    }

    public function checkIfTransversable($key, $errmsg = ":key must be a array")
    {
        $value = $this->get($key, NULL);

        if (is_array($value) || ($value instanceof \Traversable)) {
            // we are solid
        } else {
            throw new RuntimeException($this->interpolate($errmsg, [
                'key' => $key,
                'value' => $this->valueToString($value)
            ]));
        }
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

    public function combine($data)
    {
        if (is_array($data)) {
            return new DataBag(array_replace_recursive($this->data, $data));
        } elseif ($data instanceof DataBag) {
            return $this->combine($data->toArray());
        } else {
            throw new RuntimeException('Could not combine "'.gettype($data).'" data!');
        }
    }
}
