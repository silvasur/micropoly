<?php


namespace Micropoly\Search;


use Generator;
use Iterator;

class Parser
{
    public const TOK_PAROPEN = "(";
    public const TOK_PARCLOSE = ")";
    public const TOK_TAG = "#";
    public const TOK_WORD = '"';
    public const TOK_OP = "op";
    public const TOK_PROP = ":";

    private static function iterChars(string $input): Iterator
    {
        for ($i = 0; $i < mb_strlen($input); $i++)
            yield mb_substr($input, $i, 1);
    }

    /**
     * @param string $input
     * @return Iterator
     * @throws ParseError
     */
    public static function tokenize(string $input): Iterator
    {
        $chars = new CharSource($input);
        yield from self::tokenize_normal($chars);
    }

    private static function getItemAndAdvance(Iterator $input)
    {
        if (!$input->valid())
            return null;
        $out = $input->current();
        $input->next();
        return $out;
    }

    /**
     * @return Iterator
     * @throws ParseError
     */
    private static function tokenize_normal(CharSource $input): Iterator
    {
        $buf = "";

        $yieldBufAndClear = function () use (&$buf) {
            if ($buf !== "") {
                switch ($buf) {
                    case "and":
                    case "or":
                    case "not":
                        yield [self::TOK_OP, $buf];
                        break;
                    default:
                        yield [self::TOK_WORD, $buf];
                }
            }
            $buf = "";
        };

        for (;;) {
            $c = $input->getNext();
            if ($c === null) {
                break;
            }

            switch ($c) {
                case '\\':
                    $next = $input->getNext();
                    if ($next === null) {
                        $buf .= $c;
                        break 2;
                    }
                    $buf .= $next;
                    break;

                case ' ':
                case "\t":
                    yield from $yieldBufAndClear();
                    break;

                case '"':
                    yield from $yieldBufAndClear();
                    yield from self::tokenize_string($input);
                    break;

                case ':':
                    if ($buf !== "") {
                        yield [self::TOK_PROP, $buf];
                        $buf = "";
                    }
                    break;

                case '(':
                    yield from $yieldBufAndClear();
                    yield [self::TOK_PAROPEN, null];
                    break;

                case ')':
                    yield from $yieldBufAndClear();
                    yield [self::TOK_PARCLOSE, null];
                    break;

                case '#':
                    yield from $yieldBufAndClear();
                    yield from self::tokenize_tag($input);
                    break;

                default:
                    $buf .= $c;
            }
        }

        yield from $yieldBufAndClear();
        return;
    }

    /**
     * @param string $input
     * @return SearchExpr|null
     * @throws ParseError
     */
    public static function parse(string $input): ?SearchExpr
    {
        $tokens = self::tokenize($input);

        $stack = [];
        $cur = null;
        $binOp = null;
        $negated = false;

        $putExpr = function (SearchExpr $expr) use (&$cur, &$binOp, &$negated) {
            if ($negated) {
                $expr = new NotOp($expr);
            }

            $cur = $cur === null
                ? $expr
                : LogicOp::build($binOp ?? LogicOp::OP_AND, $cur, $expr);

            $binOp = null;
            $negated = false;
        };

        $setBinOp = function ($op) use (&$binOp) {
            if ($binOp !== null)
                throw new ParseError("Unexpected logic operator $op");

            $binOp = $op;
        };

        for (;;) {
            $token = self::getItemAndAdvance($tokens);
            if ($token === null)
                break;

            [$ttyp, $tdata] = $token;

            switch ($ttyp) {

                case self::TOK_TAG:
                    $putExpr(new TagExpr($tdata));
                    break;
                case self::TOK_OP:
                    switch ($tdata) {
                        case "and":
                            $setBinOp(LogicOp::OP_AND);
                            break;
                        case "or":
                            $setBinOp(LogicOp::OP_OR);
                            break;
                        case "not":
                            $negated = !$negated;
                            break;
                        default:
                            throw new \DomainException("Unexpected data for TOK_OP: $tdata");
                    }
                    break;
                case self::TOK_WORD:
                    $putExpr(new FTSExpr($tdata));
                    break;
                case self::TOK_PROP:
                    // TODO(laria): Implement this
                    throw new ParseError("Not yet supported");
                case self::TOK_PAROPEN:
                    $stack[] = [$cur, $binOp, $negated];
                    $cur = $binOp = $negated = null;
                    break;
                case self::TOK_PARCLOSE:
                    if (empty($stack))
                        throw new ParseError("Unexpected closing parenthesis");

                    $parContent = $cur;
                    [$cur, $binOp, $negated] = array_pop($stack);
                    $putExpr($parContent);
                    break;
            }
        }

        if (!empty($stack))
            throw new ParseError("Unclosed parenthesis");

        return $cur;
    }

    /**
     * @param CharSource $input
     * @return Generator
     * @throws ParseError
     */
    private static function tokenize_string(CharSource $input): Generator
    {
        $content = "";
        for (;;) {
            $c = $input->getNext();
            if ($c === null)
                throw new ParseError("Unclosed string encountered");

            switch ($c) {
                case '\\':
                    $next = $input->getNext();
                    if ($next === null)
                        throw new ParseError("Unclosed string encountered");

                    $content .= $next;
                    break;

                case '"':
                    yield [self::TOK_WORD, $content];
                    return;

                default:
                    $content .= $c;
            }
        }
    }

    /**
     * @param CharSource $input
     * @return Iterator
     */
    private static function tokenize_tag(CharSource $input): Iterator
    {
        $tag = "";

        $yieldTag = function () use (&$tag) {
            if ($tag === "")
                yield [self::TOK_WORD, "#"];
            else
                yield [self::TOK_TAG, $tag];
        };

        for (;;) {
            $c = $input->getNext();
            if ($c === null) {
                yield from $yieldTag();
                return;
            }

            switch ($c) {
                case '\\':
                    $next = $input->getNext();
                    if ($c === null) {
                        $tag .= '\\';
                        yield [self::TOK_TAG, $tag];
                        return;
                    }
                    $tag .= $next;
                    break;

                case ' ':
                case "\t":
                    yield from $yieldTag();
                    return;

                case '(':
                case ')':
                case '#':
                    $input->unget();
                    yield from $yieldTag();
                    return;

                default:
                    $tag .= $c;
            }
        }
    }
}