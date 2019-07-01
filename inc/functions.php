<?php

if (!function_exists("env")) {
    function env($key, $defaultValue = '') {
        return !empty(getenv($key)) ? getenv($key): $defaultValue;
    }
}