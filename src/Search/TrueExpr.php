<?php


namespace Micropoly\Search;


class TrueExpr implements SearchExpr
{
    public function toString(): string
    {
        return "<TrueExpr>";
    }

    public function toSQL(string $bindPrefix, bool $singleFTS): SQLSearchExpr
    {
        $sqlSearchExpr = new SQLSearchExpr();
        $sqlSearchExpr->sql = "1";
        return $sqlSearchExpr;
    }

    public function countFTSQueries(): int
    {
        return 0;
    }
}