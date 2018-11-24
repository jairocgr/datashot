<?php

namespace Datashot\Mysql;

use Datashot\Lang\DataBag;
use Datashot\Lang\Observable;
use Datashot\Util\EventBus;
use Datashot\Util\TextFileWriter;
use PDO;
use RuntimeException;

class MysqlDumper
{
    use Observable;

    const CREATING_DUMP_FILE = 'creating_output_file';
    const DUMPING_SCHEMA = 'dumping_schema';

    /**
     * @var DataBag
     */
    private $conf;

    /**
     * @var EventBus
     */
    private $bus;

    /**
     * @var TextFileWriter
     */
    private $output;

    /** @var PDO */
    private $pdo;

    public function __construct(array $conf = [])
    {
        $this->conf = new DataBag($conf);
    }

    public function touch()
    {
        $this->bus->notify(static::CREATING_DUMP_FILE, [
            'output_file' => $this->conf->output_file
        ]);

        if (file_exists($this->conf->output_file) && !unlink($this->conf->output_file)) {
            throw new RuntimeException(
                "Can't cleanup old \"{$this->conf->output_file}\" file"
            );
        }

        if (!touch($this->conf->output_file)) {
            throw new RuntimeException(
                "Can't write \"{$this->conf->output_file}\" dump file"
            );
        }

        $this->connect();
    }

    public function dumpSchema()
    {
        $this->bus->notify(static::DUMPING_SCHEMA);

        $this->message("Restoring database schema...");

        $this->eachTable(function ($table) {
            $this->dumpTableDdl($table);
        });
    }

    private function message($string)
    {
        $this->output->write("\nSELECT \"{$string}\";\n");
    }

    private function eachTable($closure)
    {
        $results = $this->pdo->query("
            SELECT table_name
            FROM information_schema.tables
            where table_schema='{$this->conf->database}'
        ");

        foreach ($results as $res) {

            $table = $res->table_name;

            call_user_func($closure, $table);
        }
    }

    private function dumpTableDdl($table)
    {
        $createStmt = $this->getCreateStatment($table);

        $this->newLine(2);
        $this->message("Creating {$table} table...");
        $this->writeln($createStmt);
        $this->newLine(2);
    }

    private function connect()
    {
        $dsn = "mysql:host={$this->conf->host};" .
               "port={$this->conf->port};" .
               "dbname={$this->conf->database}";

        $this->pdo = new PDO($dsn, $this->conf->username, $this->conf->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_TIMEOUT => static::DATABASE_TIMEOUT
        ]);
    }

    private function getCreateStatment($table)
    {
        $res = $this->pdo->query("SHOW CREATE TABLE {$table}")->fetch(PDO::FETCH_ASSOC);

        return $res[1];
    }

    private function newLine($n = 1)
    {
        $this->output->newLine($n);
    }

    private function writeln($string)
    {
        $this->output->writeln($string);
    }

    public function dumpTables()
    {
        $this->eachTable(function ($table) use (&$i) {

            $where = $this->buildWhereClause($table);

            $this->dump($table, $where);
        });
    }
}
