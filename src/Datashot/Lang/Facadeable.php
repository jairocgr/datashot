<?php

namespace Datashot\Lang;

use RuntimeException;

trait Facadeable
{
    public static function __callStatic($method, $args)
    {
        $instance = static::getInstance();

        if (! $instance) {
            throw new RuntimeException('A facade instance has not been set.');
        }

        return $instance->$method(...$args);
    }
}
