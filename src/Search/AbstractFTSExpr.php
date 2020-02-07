<?php


namespace Micropoly\Search;


abstract class AbstractFTSExpr implements SearchExpr
{
    abstract protected function fts4Query(): string;

    public function toSQL(string $bindPrefix, bool $singleFTS): SQLSearchExpr
    {
        $sqlex = new SQLSearchExpr();

        $sqlex->sql = $singleFTS
            ? "nc.note_contents MATCH :{$bindPrefix}match"
            : "n.content_row IN (
                SELECT rowid
                FROM note_contents
                WHERE note_contents MATCH :{$bindPrefix}match
            )";
        $sqlex->bindings["{$bindPrefix}match"] = $this->fts4Query();

        return $sqlex;
    }

    public function countFTSQueries(): int
    {
        return 1;
    }
}