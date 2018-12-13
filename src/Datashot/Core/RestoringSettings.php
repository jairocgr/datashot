<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;

class RestoringSettings
{
    /**
     * @var SnapperConfiguration
     */
    private $sourceSnapper;

    /**
     * @var DatabaseServer
     */
    private $targetDatabase;

    /**
     * @var DataBag
     */
    private $data;

    public function __construct(SnapperConfiguration $sourceSnapper, DatabaseServer $targetDatabase, DataBag $data)
    {
        $this->sourceSnapper = $sourceSnapper;
        $this->targetDatabase = $targetDatabase;
        $this->data = $data;
    }

    /**
     * @return SnapperConfiguration
     */
    public function getSourceSnapper()
    {
        return $this->sourceSnapper;
    }

    /**
     * @return DatabaseServer
     */
    public function getTargetDatabase()
    {
        return $this->targetDatabase;
    }

    /**
     * @return string
     */
    public function getDriver()
    {
        return $this->getSourceSnapper()->getDriver();
    }

    public function getTargetDatabaseName()
    {
        return $this->data->getr("database_name");
    }

    public function getDatabaseCharset()
    {
        return $this->getSourceSnapper()->getCharset();
    }

    public function getDatabaseCollation()
    {
        return $this->getSourceSnapper()->getCollation();
    }

    public function hasBeforeHook()
    {
        return $this->data->exists('before');
    }

    public function getBeforeHook()
    {
        return $this->data->get('before');
    }

    public function hasAfterHook()
    {
        return $this->data->exists('after');
    }

    public function getAfterHook()
    {
        return $this->data->get('after');
    }

    /**
     * @return DataBag
     */
    public function getData() {
        return $this->data;
    }

    public function get($key, $defaultValue)
    {
        return $this->data->get($key, $defaultValue);
    }

    public function set($key, $value)
    {
        $this->data->set($key, $value);
    }
}