<?php

namespace Datashot\Mysql;


class MysqlColumnType
{
    private static $NUMERIC_TYPES = [
        'bit',
        'tinyint',
        'smallint',
        'mediumint',
        'int',
        'integer',
        'bigint',
        'real',
        'double',
        'float',
        'decimal',
        'numeric'
    ];

    private static $BLOB_TYPES = [
        'tinyblob',
        'blob',
        'mediumblob',
        'longblob',
        'binary',
        'varbinary',
        'bit',
        'geometry', /* http://bugs.mysql.com/bug.php?id=43544 */
        'point',
        'linestring',
        'polygon',
        'multipoint',
        'multilinestring',
        'multipolygon',
        'geometrycollection'
    ];

    public static function fromRow($row)
    {
        $pieces = explode(" ", $row->Type);
        $type = $pieces[0];

        if ($fparen = strpos($type, "(")) {
            $type = substr($type, 0, $fparen);
            $length = intval(str_replace(")", "", substr($type, $fparen + 1)));
            $attributes = isset($pieces[1]) ? $pieces[1] : NULL;
        } else {
            $type = $type;
            $length = 0;
            $attributes = NULL;
        }

        $numeric = in_array($type, static::$NUMERIC_TYPES);
        $blob = in_array($type, static::$BLOB_TYPES);

        // for virtual columns that are of type 'Extra', column type
        // could by "STORED GENERATED" or "VIRTUAL GENERATED"
        // MySQL reference: https://dev.mysql.com/doc/refman/5.7/en/create-table-generated-columns.html
        $virtual = strpos($row->Extra, "VIRTUAL GENERATED") !== false ||
            strpos($row->Extra, "STORED GENERATED") !== false;

        return new MysqlColumnType(
            $type,
            $length,
            $attributes,
            $numeric,
            $blob,
            $virtual
        );
    }

    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $lenght;

    /**
     * @var string
     */
    private $attributes;

    /**
     * @var bool
     */
    private $numeric;

    /**
     * @var bool
     */
    private $blob;

    /**
     * @var bool
     */
    private $virtual;

    private function __construct($name, $lenght, $attributes, $numeric, $blob, $virtual)
    {
        $this->name = $name;
        $this->lenght = $lenght;
        $this->attributes = $attributes;
        $this->numeric = $numeric;
        $this->blob = $blob;
        $this->virtual = $virtual;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getLenght()
    {
        return $this->lenght;
    }

    /**
     * @return string
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @return bool
     */
    public function isNumeric()
    {
        return $this->numeric;
    }

    /**
     * @return bool
     */
    public function isBlob()
    {
        return $this->blob;
    }

    /**
     * @return bool
     */
    public function isVirtual()
    {
        return $this->virtual;
    }

    /**
     * @return bool
     */
    public function is($typeName)
    {
        return $this->name == $typeName;
    }
}