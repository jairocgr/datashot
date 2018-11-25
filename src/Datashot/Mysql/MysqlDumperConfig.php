<?php

namespace Datashot\Mysql;

use Datashot\IO\FileWriter;
use Datashot\IO\GzipFileWriter;
use Datashot\IO\TextFileWriter;
use Datashot\Lang\DataBag;

class MysqlDumperConfig
{
    use DataBag;

    /**
     * @var MysqlDumpFileWriter
     */
    private $outputFile;

    public function setWhereClause($table, $where)
    {
        $this->set("wheres.{$table}", $where);
    }

    public function setFallbackWhere($where)
    {
        $this->set("where", $where);
    }

    /**
     * @return MysqlDumpFileWriter
     */
    public function getOutputFile()
    {
        if ($this->outputFile == NULL) {

            $filepath = $this->getOutputFilePath();

            if ($this->compressOutput()) {
                $writer = new GzipFileWriter($filepath);
            } else {
                $writer = new TextFileWriter($filepath);
            }

            $this->outputFile = new MysqlDumpFileWriter($writer);
        }

        return $this->outputFile;
    }

    private function getOutputFilePath()
    {
        return $this->conf->get('output_dir', getcwd()) . DIRECTORY_SEPARATOR .
               $this->conf->get('output_file', $this->conf->database);
    }

    /**
     * @return bool
     */
    public function compressOutput()
    {
        return $this->conf->get('compress', true);
    }

    /**
     * @return bool
     */
    public function dumpAll()
    {
        return ! $this->conf->get('data_only', false);
    }

    /**
     * @return bool
     */
    public function dumpData()
    {
        return ! $this->conf->get('no_data', false);
    }
}