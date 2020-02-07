<?php


namespace Micropoly;


use InvalidArgumentException;
use Iterator;
use SQLite3;
use SQLite3Result;

class DbQuery
{
    private string $query;

    /** @var BoundVal[] */
    private array $boundVals = [];

    public function __construct(string $query)
    {
        $this->query = $query;
    }

    /**
     * @param int|array $values
     * @return string
     */
    public static function valueListPlaceholders($values): string
    {
        if (is_array($values))
            $num = count($values);
        elseif (is_int($values))
            $num = $values;
        else
            throw new InvalidArgumentException("\$values must be an int or an array");

        return implode(",", array_fill(0, $num, "?"));
    }

    public static function insert(SQLite3 $db, string $table, array $fields, array $records)
    {
        if (empty($records) || empty($fields))
            return;

        $recordTemplate = "(" . implode(",", array_fill(0, count($fields), "?")) . ")";
        $query = new self("INSERT INTO $table (" . implode(',', $fields) . ") VALUES " . implode(",", array_fill(0, count($records), $recordTemplate)));

        $i = 1;
        $fieldCount = count($fields);
        foreach ($records as $record) {
            if (count($record) !== $fieldCount)
                throw new InvalidArgumentException("count of all record fields must match field count!");

            foreach ($record as $v) {
                $query->bind($i, $v);
                $i++;
            }
        }

        $query->exec($db);
    }

    public static function insertKV(SQLite3 $db, string $table, array $kv)
    {
        self::insert($db, $table, array_keys($kv), [array_values($kv)]);
    }

    /**
     * @param mixed $where Name/Index of parameter
     * @param BoundVal|mixed $val
     * @return $this
     */
    public function bind($where, $val): self
    {
        if (!($val instanceof BoundVal))
            $val = new BoundVal($val, null);


        $this->boundVals[$where] = $val;
        return $this;
    }

    private function bindMulti(array $vals, $type, int $offset): self
    {
        foreach ($vals as $i => $v)
            $this->bind($i + $offset, new BoundVal($v, $type));

        return $this;
    }

    public function bindMultiAuto(array $vals, int $offset = 1): self { return $this->bindMulti($vals, null, $offset); }
    public function bindMultiInt(array $vals, int $offset = 1): self { return $this->bindMulti($vals, SQLITE3_INTEGER, $offset); }
    public function bindMultiFloat(array $vals, int $offset = 1): self { return $this->bindMulti($vals, SQLITE3_FLOAT, $offset); }
    public function bindMultiText(array $vals, int $offset = 1): self { return $this->bindMulti($vals, SQLITE3_TEXT, $offset); }
    public function bindMultiBlob(array $vals, int $offset = 1): self { return $this->bindMulti($vals, SQLITE3_BLOB, $offset); }
    public function bindMultiNull(array $vals, int $offset = 1): self { return $this->bindMulti($vals, SQLITE3_NULL, $offset); }

    /**
     * @param SQLite3 $db
     * @param callable|null $cb
     * @return mixed Result of callback or null, if none given
     * @throws DBError
     */
    public function exec(SQLite3 $db, ?callable $cb = null)
    {
        $stmt = $db->prepare($this->query);
        if ($stmt === false)
            throw new DBError("Prepare failed", $this->query);
        foreach ($this->boundVals as $where => $boundVal)
            $boundVal->bind($stmt, $where);

        $res = $stmt->execute();
        if ($res === false) {
            throw new DBError("execute failed", $this->query);
        }

        $out = $cb ? $cb($res) : null;

        $res->finalize();
        $stmt->close();

        return $out;
    }

    public function fetchRow(SQLite3 $db, int $fetchMode = SQLITE3_NUM): ?array
    {
        return $this->exec($db, static function (SQLite3Result $res) use ($fetchMode) {
            return $res->numColumns() ? $res->fetchArray($fetchMode) : null;
        });
    }

    public function fetchRowAssoc(SQLite3 $db): ?array { return $this->fetchRow($db, SQLITE3_ASSOC); }

    public function fetchRows(SQLite3 $db, int $fetchMode = SQLITE3_NUM): array
    {
        return $this->exec($db, static function (SQLite3Result $res) use ($fetchMode) {
            if (!$res->numColumns())
                return [];

            $out = [];

            while (($row = $res->fetchArray($fetchMode)))
                $out[] = $row;

            return $out;
        });
    }

    public function fetchRowsAssoc(SQLite3 $db): array { return $this->fetchRows($db, SQLITE3_ASSOC); }

    public function fetchIndexedRows(SQLite3 $db, ...$keys): array
    {
        return $this->exec($db, static function (SQLite3Result $res) use ($keys) {
            if (!$res->numColumns())
                return [];

            $out = [];

            while (($row = $res->fetchArray(SQLITE3_ASSOC))) {
                $cursor =& $out;

                foreach ($keys as $k)
                    $cursor =& $cursor[$row[$k]];

                $cursor = $row;
            }

            return $out;
        });
    }

    public function fetchIndexedValues(SQLite3 $db, $val, ...$keys): array
    {
        return array_map(fn ($row) => $row[$val] ?? null, $this->fetchIndexedRows($db, ...$keys));
    }

    public function fetchIndexedAllRows(SQLite3 $db, ...$keys): array
    {
        return $this->exec($db, static function (SQLite3Result $res) use ($keys) {
            if (!$res->numColumns())
                return [];

            $out = [];

            while (($row = $res->fetchArray(SQLITE3_ASSOC))) {
                $cursor =& $out;

                foreach ($keys as $k)
                    $cursor =& $cursor[$row[$k]];

                $cursor[] = $row;
            }

            return $out;
        });
    }
}