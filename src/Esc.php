<?php


namespace Micropoly;


class Esc
{
    public const HTML = 1;
    public const NL2BR = 2;
    public const HTML_WITH_BR = self::HTML | self::NL2BR;

    public static function e(string $s, int $flags = self::HTML): string
    {
        if ($flags & self::HTML)
            $s = htmlspecialchars($s);
        if ($flags & self::NL2BR)
            $s = nl2br($s);
        return $s;
    }
}