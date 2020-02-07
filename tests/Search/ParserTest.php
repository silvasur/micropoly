<?php

namespace Micropoly\Search;

use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{

    /**
     * @covers \Micropoly\Search\Parser::tokenize
     * @dataProvider tokenizeDataProvider
     */
    public function testTokenize($input, $want)
    {
        $have = [];
        foreach (Parser::tokenize($input) as $tok)
            $have[] = $tok;

        $this->assertSame($want, $have);
    }

    public function tokenizeDataProvider()
    {
        return [
            ["", []],
            ["hello", [
                [Parser::TOK_WORD, "hello"],
            ]],
            ["hello world", [
                [Parser::TOK_WORD, "hello"],
                [Parser::TOK_WORD, "world"],
            ]],
            ['"hello world"', [
                [Parser::TOK_WORD, "hello world"],
            ]],
            ['"hello\\"quote\\\\"', [
                [Parser::TOK_WORD , 'hello"quote\\'],
            ]],
            ["foo\\ bar", [
                [Parser::TOK_WORD, "foo bar"],
            ]],
            ['foo\\\\bar\\"baz', [
                [Parser::TOK_WORD, 'foo\\bar"baz'],
            ]],
            ['foo\\', [
                [Parser::TOK_WORD, 'foo\\'],
            ]],
            ["#foo #bar", [
                [Parser::TOK_TAG, "foo"],
                [Parser::TOK_TAG, "bar"],
            ]],
            ["#foo\\ bar", [
                [Parser::TOK_TAG, "foo bar"],
            ]],
            ["and or not ()( )", [
                [Parser::TOK_OP, "and"],
                [Parser::TOK_OP, "or"],
                [Parser::TOK_OP, "not"],
                [Parser::TOK_PAROPEN, null],
                [Parser::TOK_PARCLOSE, null],
                [Parser::TOK_PAROPEN, null],
                [Parser::TOK_PARCLOSE, null],
            ]],
            ["(#foo)", [
                [Parser::TOK_PAROPEN, null],
                [Parser::TOK_TAG, "foo"],
                [Parser::TOK_PARCLOSE, null],
            ]],
            ["foo:bar", [
                [Parser::TOK_PROP, "foo"],
                [Parser::TOK_WORD, "bar"],
            ]],
        ];
    }

    public function testTokenizeFailUnclosedString()
    {
        $this->expectException(ParseError::class);
        foreach (Parser::tokenize('foo "bar') as $_);
    }

    /**
     * @param string $input
     * @param bool|null|SearchExpr $exprOrFalseForErr
     * @dataProvider parseDataProvider
     */
    public function testParse(string $input, $exprOrFalseForErr)
    {
        if ($exprOrFalseForErr === false)
            $this->expectException(ParseError::class);

        $have = Parser::parse($input);
        if ($have !== null)
            $have = $have->toString();

        $want = $exprOrFalseForErr === null ? null : $exprOrFalseForErr->toString();

        $this->assertSame($want, $have);
    }

    public function parseDataProvider()
    {
        return [
            ["", null],
            ["(", false],
            [")", false],
            ["foo", new FTSExpr("foo")],
            ["foo #bar", new LogicOp(LogicOp::OP_AND, new FTSExpr("foo"), new TagExpr("bar"))],
            ["(foo and #bar) or not baz", new LogicOp(
                LogicOp::OP_OR, new LogicOp(
                LogicOp::OP_AND,
                new FTSExpr("foo"),
                new TagExpr("bar")
            ), new NotOp(new FTSExpr("baz"))
            )],
            ["foo bar", new FTSLogicOp(LogicOp::OP_AND, new FTSExpr("foo"), new FTSExpr("bar"))],
        ];
    }
}
