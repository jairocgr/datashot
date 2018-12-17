<?php

namespace Datashot\Core;

interface SnapUploader
{
    const UPLOADING = 'snap_uploading';
    const DONE = 'done_uploading';

    function upload();

    /**
     * @return string
     */
    function getSourceFileName();

    /**
     * @return Repository
     */
    function getTargetRepository();
}