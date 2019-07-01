<?php


namespace Datashot\Core;

use PHPUnit\Framework\TestCase;

class PathTest extends TestCase
{
    public function test()
    {
        $path = new Path('/folder/path//sub-path/snapp.gz');

        $this->assertEquals(TRUE, $path->absolute());
        $this->assertEquals(FALSE, $path->relative());
        $this->assertEquals(FALSE, $path->pointingToRoot());
        $this->assertEquals(FALSE, $path->isGlobPattern());

        $this->assertEquals('/folder/path/sub-path', $path->getSubPath());
        $this->assertEquals('snapp', $path->getItemName());

        $this->assertEquals('/folder/path/sub-path/snapp', $path->getFullPath());
        $this->assertEquals('/folder/path/sub-path/snapp', strval($path));
    }

    public function testAbsoluteDirectoryPath()
    {
        $path = new Path('/tmp/upd/bb/');

        $this->assertEquals(TRUE, $path->absolute());
        $this->assertEquals(FALSE, $path->relative());
        $this->assertEquals(FALSE, $path->pointingToRoot());
        $this->assertEquals(FALSE, $path->isGlobPattern());

        $this->assertEquals('/tmp/upd', $path->getSubPath());
        $this->assertEquals('bb', $path->getItemName());

        $this->assertEquals('/tmp/upd/bb', $path->getFullPath());
        $this->assertEquals('/tmp/upd/bb', strval($path));
    }

    public function testOnName()
    {
        $path = new Path('snapp.gz');

        $this->assertEquals(FALSE, $path->absolute());
        $this->assertEquals(TRUE, $path->relative());
        $this->assertEquals(FALSE, $path->pointingToRoot());
        $this->assertEquals(FALSE, $path->isGlobPattern());

        $this->assertEquals('', $path->getSubPath());
        $this->assertEquals('snapp', $path->getItemName());

        $this->assertEquals('snapp', $path->getFullPath());
        $this->assertEquals('snapp', strval($path));
    }

    public function testGlob()
    {
        $path = new Path('snapp_all_*.gz');

        $this->assertEquals(FALSE, $path->pointingToRoot());
        $this->assertEquals(TRUE, $path->isGlobPattern());

        $this->assertEquals('', $path->getSubPath());
        $this->assertEquals('snapp_all_*', $path->getItemName());

        $this->assertEquals('snapp_all_*', $path->getFullPath());
        $this->assertEquals('snapp_all_*', strval($path));
    }

    public function testGlobWithPath()
    {
        $path = new Path('path/to/dump_*_min');

        $this->assertEquals(FALSE, $path->pointingToRoot());
        $this->assertEquals(TRUE, $path->isGlobPattern());

        $this->assertEquals('path/to', $path->getSubPath());
        $this->assertEquals('dump_*_min', $path->getItemName());

        $this->assertEquals('path/to/dump_*_min', $path->getFullPath());
        $this->assertEquals('path/to/dump_*_min', strval($path));
    }

    public function testEmpty()
    {
        $path = new Path('');

        $this->assertEquals(TRUE, $path->pointingToRoot());
        $this->assertEquals(FALSE, $path->isGlobPattern());
        $this->assertEquals(TRUE, $path->isEmpty());

        $this->assertEquals('', $path->getSubPath());
        $this->assertEquals('', $path->getItemName());
    }

    public function testRoot()
    {
        $path = new Path('/');

        $this->assertEquals(TRUE, $path->pointingToRoot());
        $this->assertEquals(FALSE, $path->isGlobPattern());
        $this->assertEquals(FALSE, $path->isEmpty());

        $this->assertEquals('/', $path->getSubPath());
        $this->assertEquals('', $path->getItemName());
    }
}