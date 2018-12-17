<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;
use Datashot\Lang\DataWrapper;

class Repository
{
    private static $SUPPORTED_DRIVERS = [ 's3' ];

    /**
     * @var string
     */
    private $name;

    use DataWrapper;

    public function __construct($name, DataBag $data)
    {
        $this->name = $name;
        $this->data = $data;

        $this->validate();
    }

    public function getDriver() {
        return $this->data->get('driver');
    }

    private function validate()
    {
        $this->data->oneOf('driver', static::$SUPPORTED_DRIVERS, "Invalid :key :value for {$this->name} repository");
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function getName()
    {
        return $this->name;
    }
}