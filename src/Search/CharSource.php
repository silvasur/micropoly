<?php


namespace Micropoly\Search;


class CharSource
{
    private string $s;
    private int $i = 0;
    private int $len;

    public function __construct(string $s)
    {
        $this->s = $s;
        $this->len = mb_strlen($s);
    }

    public function getNext(): ?string
    {
        if ($this->i >= $this->len)
            return null;

        $c = mb_substr($this->s, $this->i, 1);
        $this->i++;
        return $c;
    }

    public function unget(): void
    {
        $this->i = max(0, $this->i - 1);
    }
}