<?php


namespace Micropoly\Search;


class NotOp implements SearchExpr
{
    private SearchExpr $expr;

    public function __construct(SearchExpr $expr)
    {
        $this->expr = $expr;
    }

    public function toString(): string
    {
        return "not ({$this->expr->toString()})";
    }

    public function toSQL(string $bindPrefix, bool $singleFTS): SQLSearchExpr
    {
        $sqlex = $this->expr->toSQL($bindPrefix, $singleFTS);
        $sqlex->sql = "(NOT ({$sqlex->sql}))";
        return $sqlex;
    }

    public function countFTSQueries(): int
    {
        return $this->expr->countFTSQueries();
    }
}