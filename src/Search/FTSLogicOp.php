<?php


namespace Micropoly\Search;


class FTSLogicOp extends AbstractFTSExpr
{
    private string $op;
    private AbstractFTSExpr $a;
    private AbstractFTSExpr $b;

    /**
     * FTSLogicOp constructor.
     * @param string $op
     * @param AbstractFTSExpr $a
     * @param AbstractFTSExpr $b
     */
    public function __construct(string $op, AbstractFTSExpr $a, AbstractFTSExpr $b)
    {
        if (!LogicOp::checkOp($op))
            throw new \DomainException("{$op} is not a valid operator");

        $this->op = $op;
        $this->a = $a;
        $this->b = $b;
    }

    private const FTSOPS = [
        LogicOp::OP_AND => "",
        LogicOp::OP_OR => "OR",
    ];

    protected function fts4Query(): string
    {
        assert(isset(self::FTSOPS[$this->op]));
        $ftsop = self::FTSOPS[$this->op];

        return "({$this->a->fts4Query()} {$ftsop} {$this->b->fts4Query()})";
    }

    public function toString(): string
    {
        return "({$this->a->toString()} FTS-{$this->op} {$this->b->toString()})";
    }
}
