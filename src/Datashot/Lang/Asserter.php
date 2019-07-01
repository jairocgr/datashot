<?php

namespace Datashot\Lang;

use InvalidArgumentException;
use Traversable;

class Asserter
{
    protected static $instance;

    use Facadeable;

    /**
     * @return Asserter
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Asserter();
        }

        return self::$instance;
    }

    /**
     * @return bool
     */
    public function stringfyable($value)
    {
        return $value === null || is_scalar($value)
            || (is_object($value) && method_exists($value, '__toString'));
    }

    /**
     * @return bool
     */
    public function notEmptyString($value)
    {
        return !$this->emptyString($value);
    }

    /**
     * @return bool
     */
    public function emptyString($value)
    {
        return empty(trim(strval($value)));
    }

    /**
     * @return string
     */
    public function valueToString($value)
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
                return $this->valueToString($value->__toString());
            }

            return get_class($value);
        }

        if (is_resource($value)) {
            return 'resource';
        }

        if (is_string($value)) {
            return $value;
        }

        return strval($value);
    }

    /**
     * @return string
     */
    public function vdump($value)
    {
        return "\"{$this->valueToString($value)}\"";
    }

    public function raise($errmsg, $args = [])
    {
        foreach ($args as $key => $value) {
            $errmsg = str_replace(":{$key}", $this->vdump($value), $errmsg);
        }

        throw new InvalidArgumentException($errmsg);
    }

    /**
     * @param $value
     * @return boolean
     */
    public function integerfyable($value)
    {
        if ($this->stringfyable($value)) {
            $value = trim($this->valueToString($value));
            return preg_match("/^[0-9]+$/", $value) ? TRUE : FALSE;
        } elseif (is_integer($value)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function transversable($value)
    {
        return is_array($value) || $value instanceof Traversable;
    }

    public function collectionOf($values, $class)
    {
        foreach ($values as $value) {
            if (get_class($value) != $class) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public function booleanable($value)
    {
        $allowedValues = [
            // Dont allow float numbers, they are way too ambiguous for my taste
            1,
            0,
            TRUE,
            FALSE,
            "TRUE",
            "FALSE",
            "true",
            "false",
            "True",
            "False",
        ];

        foreach ($allowedValues as $allowed) {
            if ($value === $allowed) {
                return TRUE;
            }
        }

        return false;
    }
}