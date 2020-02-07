<?php


namespace Micropoly;


use Exception;

class DBError extends Exception
{
    private string $msg;
    private string $sql;

    /**
     * DBError constructor.
     * @param string $msg
     * @param string $sql
     */
    public function __construct(string $msg, string $sql)
    {
        $this->msg = $msg;
        $this->sql = $sql;

        parent::__construct($this->buildMessage());
    }

    private function buildMessage(): string
    {
        return "{$this->msg}. SQL was: {$this->sql}";
    }
}