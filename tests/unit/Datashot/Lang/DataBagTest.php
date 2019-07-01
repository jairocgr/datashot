<?php

namespace Datashot\Lang;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class DataBagTest extends TestCase
{
    /**
     * @var DataBag
     */
    private $data;

    protected function setUp()
    {
        $this->data = new DataBag([

            'project_name' => 'datashot',

            'output_dir' => __DIR__,

            'words' => [ 'Lorem', 'ipsum', 'dolor', 'sit', 'amet' ],

            'row_count' => 232432,

            'latitude' => 2038242.323,

            'active' => TRUE,

            'repositories' => [
                's3' => [
                    'driver' => 's3',
                    'host' => 'tcp://s3',
                ],

                'disk' => [
                    'driver' => 's3',
                    'host' => 'tcp://disk'
                ],

                'repo1' => [
                    'host' => 'tcp://repo1',
                    'driver' => 's3',
                    'region' => 'us-east-1',
                ],

                'repo2' => [
                    'host' => 'tcp://repo2',
                    'driver' => 's3',
                    'region' => 'us-east-1',
                ],
            ],

            'database_servers' => [
                'workbench1' => [
                    'driver'    => 'mysql',

                    'host'      => 'database.host',
                    'port'      => 3306,

                    'user'  => 'admin',
                    'password'  => 'FsHEpLd2+LtQjTs+ZnJ+jA=='
                ]
            ],

            'user' => '{database_servers.workbench1.user}',
            'rows' => '{row_count}',
            'server' => '{database_servers}',
        ]);
    }

    public function test()
    {
        $this->assertEquals('datashot', $this->data->project_name);

        $this->assertEquals('ipsum', ($this->data->get('words'))[1]);
        $this->assertEquals('ipsum', $this->data->words[1]);
        $this->assertEquals('ipsum', $this->data['words'][1]);

        $this->assertTrue($this->data->get('active'));
        $this->assertTrue($this->data->active);
        $this->assertTrue($this->data['active']);

        $this->assertEquals(DataBag::class, get_class($this->data->database_servers->workbench1));
        $this->assertEquals('admin', $this->data->get('database_servers')->get('workbench1')->user);
        $this->assertEquals('admin', $this->data->database_servers->workbench1->user);
        $this->assertEquals('admin', $this->data['database_servers']['workbench1']->user);

        $this->assertEquals('admin', $this->data->{'database_servers.workbench1'}->user);

        $this->assertEquals('default_value', $this->data->get('missing_key', 'default_value'));
    }

    public function testResolveReferences() {
        $this->assertEquals('admin', $this->data->user);
        $this->assertEquals('232432', $this->data->rows);
        $this->assertEquals('{database_servers}', $this->data->server);
    }

    public function testExtractor()
    {
        $this->assertEquals('admin', $this->data->extract('user', function ($value) {
            return $value;
        }));

        $this->assertEquals(NULL, $this->data->extract('user70', function ($value) {
            return $value;
        }));

        $this->assertEquals('undefined', $this->data->extract('user70', 'undefined', function ($value) {
            return $value;
        }));
    }

    public function testDotSyntax()
    {
        $this->assertEquals('tcp://disk', $this->data->get('repositories.disk.host'));

        $this->data->set('repositories.disk.host', '[new_value]');
        $this->assertEquals('[new_value]', $this->data->get('repositories.disk.host'));

        $this->data['repositories.disk.host'] = '[other_value]';
        $this->assertEquals('[other_value]', $this->data->get('repositories.disk.host'));

        $this->data['repositories']['disk']['host'] = '[array]';
        $this->assertEquals('[array]', $this->data->get('repositories.disk.host'));

        $this->data['repositories']['disk']['port'] = 22;
        $this->assertEquals(22, $this->data->get('repositories.disk.port'));
        $this->assertEquals(22, $this->data['repositories']['disk']['port']);
    }

    public function testGetSetArraySyntax()
    {
        $this->assertEquals(232432, $this->data['row_count']);

        $this->data['row_count'] = 300;
        $this->assertEquals(300, $this->data['row_count']);
    }

    public function testIterator()
    {
        $iterations = 0;

        foreach ($this->data->repositories as $key => $repository) {
            $this->assertEquals("tcp://{$key}", $repository->host);
            $iterations++;
        }

        $this->assertEquals(4, $iterations);
    }

    public function testIterateAndSet() {
        foreach ($this->data->repositories as $key => $val) {
            $this->assertEquals('s3', $val->driver);
            $this->data->set("repositories.{$key}.region", "brazil");
            $this->data->set("repositories.{$key}.name", "repo");
            $val->write_only = '16';
        }

        $this->assertEquals('brazil', $this->data->get('repositories.repo1.region'));
        $this->assertEquals('repo', $this->data->get('repositories.repo1.name'));
        $this->assertEquals('16', $this->data->get('repositories.repo1.write_only'));

        $this->assertEquals('brazil', $this->data->get('repositories.repo2.region'));
        $this->assertEquals('repo', $this->data->get('repositories.repo2.name'));

        $this->assertEquals('brazil', ($this->data->toArray())['repositories']['repo2']['region']);
    }

    public function testSetSimpleValues()
    {
        $this->data->set('data1', 'strval');

        $this->data->set([
            'data2' => 'mass_strval_2',
            'data3' => 'mass_strval_3'
        ]);

        $this->assertEquals('strval', $this->data->get('data1'));
        $this->assertEquals('mass_strval_2', $this->data->get('data2'));
        $this->assertEquals('mass_strval_3', $this->data->get('data3'));
    }

    public function testSetArray()
    {
        $this->data->set('arr1', [ 2, 4, 8]);

        $this->assertEquals(8, ($this->data->get('arr1'))[2]);
    }

    public function testSetObject()
    {
        $this->data->set('databag1', [
            'param1' => 'val1',
            'param2' => 2
        ]);

        $databag = $this->data->get('databag1');

        $this->assertEquals(DataBag::class, get_class($databag));
        $this->assertEquals('val1', $databag->param1);
        $this->assertEquals(2, $databag->param2);
    }

    public function testSetDotSyntax()
    {
        $this->data->set('databag1', [
            'param1' => 'val1',
            'param2' => 2
        ]);

        $this->data->set('databag1.param2', 'value2');

        $databag = $this->data->get('databag1');

        $this->assertEquals(DataBag::class, get_class($databag));
        $this->assertEquals('val1', $databag->param1);
        $this->assertEquals('value2', $databag->param2);
    }

    public function testExists() {
        $this->assertTrue($this->data->exists('active'));
        $this->assertFalse($this->data->exists('active::'));
    }

    public function testSetEmptyKey()
    {
        $this->expectException(RuntimeException::class);
        $this->data->set('', 'value');
    }

    public function testGetEmptyKey()
    {
        $this->expectException(RuntimeException::class);
        $this->data->get('');
    }

    public function testAppendValue()
    {
        $bag = new DataBag();
        $bag[] = 'value';

        $this->assertEquals('value', ($bag->toArray())[0]);
    }
}