<?php

namespace Datashot\Mysql;

use Datashot\IO\FileWriter;
use Datashot\IO\GzipFileWriter;
use Datashot\IO\TextFileWriter;
use Datashot\Lang\DataBag;

class MysqlDumperConfig
{
    use DataBag;

    public function setWhereClause($table, $where)
    {
        $this->set("wheres.{$table}", $where);
    }

    public function setFallbackWhere($where)
    {
        $this->set("where", $where);
    }

    /**
     * @return FileWriter
     */
    public function getOutputFile()
    {
        $filepath = $this->getOutputFilePath();

        if ($this->compressOutput()) {
            return new GzipFileWriter($filepath);
        } else {
            return new TextFileWriter($filepath);
        }
    }

    private function getOutputFilePath()
    {
        return $this->conf->get('output_dir', getcwd()) . DIRECTORY_SEPARATOR .
               $this->conf->get('output_file', $this->conf->database);
    }

    private function compressOutput()
    {
        return $this->conf->get('compress', true);
    }
}