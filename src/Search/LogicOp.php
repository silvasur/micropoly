<?php


namespace Micropoly\Search;


class LogicOp implements SearchExpr
{
    public const OP_AND = "and";
    public const OP_OR = "or";

    private const SQLOPS = [
        self::OP_AND => "AND",
        self::OP_OR => "OR",
    ];

    private string $op;
    private SearchExpr $a;
    private SearchExpr $b;

    public function __construct(string $op, SearchExpr $a, SearchExpr $b)
    {
        if (!self::checkOp($op))
            throw new \DomainException("{$op} is not a valid operator");

        $this->op = $op;
        $this->a = $a;
        $this->b = $b;
    }

    public static function build(string $op, SearchExpr $a, SearchExpr $b): SearchExpr
    {
        return $a instanceof AbstractFTSExpr && $b instanceof AbstractFTSExpr
            ? new FTSLogicOp($op, $a, $b)
            : new self($op, $a, $b);
    }

    /**
     * @param string $op
     * @return bool
     */
    public static function checkOp(string $op): bool
    {
        return in_array($op, [
            self::OP_AND,
            self::OP_OR,
        ]);
    }

    public function getA(): SearchExpr { return $this->a; }
    public function getB(): SearchExpr { return $this->b; }
    public function getOp(): string { return $this->op; }

    public function toString(): string
    {
        return "({$this->a->toString()}) {$this->op} ({$this->b->toString()})";
    }

    public function toSQL($bindPrefix, bool $singleFTS): SQLSearchExpr
    {
        $sqlex = new SQLSearchExpr();

        $a = $this->a->toSQL("a_$bindPrefix", $singleFTS);
        $b = $this->b->toSQL("b_$bindPrefix", $singleFTS);
        $sqlop = self::SQLOPS[$this->op];
        assert($sqlop);

        $sqlex->sql = "(({$a->sql}) {$sqlop} ({$b->sql}))";
        $sqlex->bindings = array_merge($a->bindings, $b->bindings);

        return $sqlex;
    }

    public function countFTSQueries(): int
    {
        return $this->a->countFTSQueries() + $this->b->countFTSQueries();
    }
}