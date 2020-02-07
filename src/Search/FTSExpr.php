<?php


namespace Micropoly\Search;


class FTSExpr extends AbstractFTSExpr
{
    private string $term;

    public function __construct(string $term)
    {
        $this->term = $term;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    protected function fts4Query(): string
    {
        return '"' . str_replace('"', '""', $this->term) . '"';
    }

    public function toString(): string
    {
        return '"' . preg_replace_callback('/(["\\\\])/', fn($s) => "\\$s", $this->term) . '"';
    }
}