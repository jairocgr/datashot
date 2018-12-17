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
                    'host' => 'tcp://s3',
                ],
                'disk' => [
                    'host' => 'tcp://disk'
                ],
            ],

            'database_servers' => [
                'workbench1' => [
                    'driver'    => 'mysql',

                    'host'      => 'database.host',
                    'port'      => 3306,

                    'username'  => 'admin',
                    'password'  => 'FsHEpLd2+LtQjTs+ZnJ+jA=='
                ]
            ],

            'username' => '{database_servers.workbench1.username}',
            'rows' => '{row_count}',
            'server' => '{database_servers}'
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
        $this->assertEquals('admin', $this->data->get('database_servers')->get('workbench1')->username);
        $this->assertEquals('admin', $this->data->database_servers->workbench1->username);
        $this->assertEquals('admin', $this->data['database_servers']['workbench1']->username);

        $this->assertEquals('admin', $this->data->{'database_servers.workbench1'}->username);

        $this->assertEquals('default_value', $this->data->get('missing_key', 'default_value'));
    }

    public function testResolveReferences() {
        $this->assertEquals('admin', $this->data->username);
        $this->assertEquals('232432', $this->data->rows);
        $this->assertEquals('{database_servers}', $this->data->server);
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

        $this->assertEquals(2, $iterations);
    }

    public function testGetRequired()
    {
        $this->expectException(RuntimeException::class);

        $this->data->getr('missing_key');
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

    public function testExists() {
        $this->assertTrue($this->data->exists('active'));
        $this->assertFalse($this->data->exists('active::'));
    }

    public function testEmpty() {

        $this->data->checkIfNotEmpty('latitude');

        $this->expectException(RuntimeException::class);

        $this->data->checkIfNotEmpty('missing_key');
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