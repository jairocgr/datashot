<?php

namespace Datashot\IO;

interface FileWriter
{
    function write($string);

    function flush();

    function close();

    function open();
}
