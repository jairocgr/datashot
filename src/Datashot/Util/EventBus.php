<?php

namespace Datashot\Util;

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

    public function notify($event, ...$args)
    {
        $listeners = isset($this->listeners[$event])
            ? $this->listeners[$event] : [];

        foreach ($listeners as $listener) {
            call_user_func($listener, $args);
        }
    }
}

