<?php

namespace Datashot\Util;

use Closure;
use Exception;

/**
 * Simple EventBus implementation for internal use
 */
class EventBus {

    /** @var array */
    private $listeners = [];

    public function on($event, $callback)
    {
        $this->addListener($event, $callback);
    }

    private function addListener($event, Closure $callback)
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }

        $this->listeners[$event][] = $callback;
    }

    public function publish($event, ...$args)
    {
        $listeners = isset($this->listeners[$event])
            ? $this->listeners[$event] : [];

        foreach ($listeners as $listener) {
            try {
                call_user_func_array($listener, $args);
            } catch (Exception $e) {
                $this->publish(get_class($e), $e);
            }
        }
    }
}

