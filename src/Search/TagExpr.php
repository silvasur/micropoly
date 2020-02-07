<?php


namespace Micropoly\Search;


class TagExpr implements SearchExpr
{
    private string $tag;

    public function __construct(string $tag)
    {
        $this->tag = $tag;
    }

    public function getTag(): string { return $this->tag; }

    public function toString(): string
    {
        return "#{$this->tag}";
    }

    public function toSQL(string $bindPrefix, bool $singleFTS): SQLSearchExpr
    {
        $sqlex = new SQLSearchExpr();

        $sqlex->sql = "EXISTS (
            SELECT 1
            FROM tags t
            WHERE t.tag = :{$bindPrefix}tag
                AND t.note_id = n.id
        )";
        $sqlex->bindings["{$bindPrefix}tag"] = $this->tag;

        return $sqlex;
    }

    public function countFTSQueries(): int
    {
        return 0;
    }
}