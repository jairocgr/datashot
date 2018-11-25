<?php

namespace Datashot\Mysql;

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

            return new MysqlDumpFileWriter($writer);
        }

        return $this->outputFile;
    }

    /**
     * @return string
     */
    private function getOutputFilePath()
    {
        return $this->get('output_dir', getcwd()) . DIRECTORY_SEPARATOR .
               $this->get('output_file', $this->database) . '.' .
               $this->getOutputExtension();
    }

    /**
     * @return bool
     */
    public function compressOutput()
    {
        return $this->get('compress', true);
    }

    /**
     * @return bool
     */
    public function dumpAll()
    {
        return ! $this->get('data_only', false);
    }

    /**
     * @return bool
     */
    public function dumpData()
    {
        return ! $this->get('no_data', false);
    }

    private function getOutputExtension()
    {
        return ($this->compressOutput() ? 'gz' : 'sql');
    }

    public function hasRowTransformer($table)
    {
        $transformers = $this->get('row_transformers');

        return isset($transformers[$table]);
    }

    public function getRowTransformer($table)
    {
        return $this->get("row_transformers.{$table}");
    }
}