<?php

namespace Datashot\Core;

use Datashot\Lang\DataBag;
use Datashot\Lang\DataWrapper;

class UploadSettings
{
    use DataWrapper;

    /**
     * @var SnapperConfiguration
     */
    private $sourceSnapper;

    /**
     * @var Repository
     */
    private $targetRepository;

    public function __construct(SnapperConfiguration $sourceSnapper, Repository $targetRepository, DataBag $data)
    {
        $this->sourceSnapper = $sourceSnapper;
        $this->targetRepository = $targetRepository;
        $this->data = $data;
    }

    /**
     * @return SnapperConfiguration
     */
    public function getSourceFilePath()
    {
        return $this->sourceSnapper->getOutputFilePath();
    }

    /**
     * @return Repository
     */
    public function getTargetRepository()
    {
        return $this->targetRepository;
    }

    /**
     * @return string
     */
    public function getTargetFolder()
    {
        $target = $this->targetRepository->get('target_folder', '/');

        return $this->data->get('target_folder', $target);
    }

    /**
     * @return string
     */
    public function getSourceFileName()
    {
        return $this->sourceSnapper->getOutputFileName();
    }

    /**
     * @return string
     */
    public function getDriver()
    {
        return $this->targetRepository->getDriver();
    }
}