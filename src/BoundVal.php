<?php


namespace Micropoly;


use SQLite3Stmt;

class BoundVal
{
    private $val;
    private $type = null;

    public function __construct($val, $type = null)
    {
        $this->val = $val;
        $this->type = $type;
    }

    public function getVal() { return $this->val; }
    public function getType() { return $this->type; }

    public static function ofInt($val): self { return new self($val, SQLITE3_INTEGER); }
    public static function ofFloat($val): self { return new self($val, SQLITE3_FLOAT); }
    public static function ofText($val): self { return new self($val, SQLITE3_TEXT); }
    public static function ofBlob($val): self { return new self($val, SQLITE3_BLOB); }
    public static function ofNull(): self { return new self(null, SQLITE3_NULL); }

    public function bind(SQLite3Stmt $stmt, $where): void
    {
        if ($this->type === null)
            $stmt->bindValue($where, $this->val);
        else
            $stmt->bindValue($where, $this->val, $this->type);
    }
}