<?php

namespace Datashot\IO;

interface FileWriter
{
    function write($string);

    function writeln($string);

    function newLine($count = 1);

    function flush();

    function close();

    function open();
}
