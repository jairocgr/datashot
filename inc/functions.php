<?php

if (!function_exists("env")) {
    function env($key, $defaultValue = '') {
        if (!empty(getenv($key))) {
            return getenv($key);
        } elseif (isset($_ENV[$key])) {
            return $_ENV[$key];
        } else {
            return $defaultValue;
        }
    }
}
