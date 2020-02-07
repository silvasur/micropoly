<?php


namespace Micropoly\Search;


class FTSNotExpr extends AbstractFTSExpr
{
    private AbstractFTSExpr $expr;

    /**
     * FTSNotExpr constructor.
     * @param AbstractFTSExpr $expr
     */
    public function __construct(AbstractFTSExpr $expr)
    {
        $this->expr = $expr;
    }

    protected function fts4Query(): string
    {
        return "-{$this->expr->fts4Query()}";
    }

    public function toString(): string
    {
        return "(FTS-NOT {$this->expr->toString()})";
    }
}