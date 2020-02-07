<?php


namespace Micropoly\Search;


interface SearchExpr
{
    public function toString(): string;

    public function toSQL(string $bindPrefix, bool $singleFTS): SQLSearchExpr;

    public function countFTSQueries(): int;
}