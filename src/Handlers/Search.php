<?php


namespace Micropoly\Handlers;

use Micropoly\Env;
use Micropoly\Handler;
use Micropoly\Models\Note;
use Micropoly\Search\ParseError;
use Micropoly\Search\Parser;
use Micropoly\Search\SearchResult;
use Micropoly\Search\TrueExpr;

class Search implements Handler
{
    public function handle(Env $env, array $variables)
    {
        $vars = ["query" => $_GET["q"] ?? ""];

        try {
            $expr = isset($_GET["q"])
                ? (Parser::parse($_GET["q"]) ?? new TrueExpr())
                : new TrueExpr();

            $results = SearchResult::search($env->db(), $expr);
            $vars["results"] = $results;
        } catch (ParseError $e) {
            $vars["error"] = $e->getMessage();
        }

        echo $env->twig()->render("/search.twig", $vars);
    }
}