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
}