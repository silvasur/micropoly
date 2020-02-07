<?php

namespace Micropoly;

use SQLite3;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Env
{
    private array $config;

    private function __construct() { }

    private array $lazyLoaded = [];

    private function lazy(string $ident, callable $callback)
    {
        if (!isset($this->lazyLoaded[$ident])) {
            $this->lazyLoaded[$ident] = $callback();
        }
        return $this->lazyLoaded[$ident];
    }

    public static function fromConfig(array $config)
    {
        $env = new self;
        $env->config = $config;
        return $env;
    }

    public function documentRoot(): string { return "/"; }

    public function twig(): Environment
    {
        return $this->lazy("twig", function () {
            $loader = new FilesystemLoader($this->config["templates_path"]);
            $env = new Environment($loader, [
                "cache" => $this->config["templates_cache"],
            ]);

            $env->addFunction(new TwigFunction("url", function (string $url, ...$args) {
                return $this->documentRoot() . sprintf($url, ...$args);
            }, ["is_variadic" => true]));

            $env->addFilter(new TwigFilter("search_escape", static function (string $s) {
                $s = str_replace("\\", "\\\\", $s);
                $s = str_replace("#", "\\#", $s);
                $s = str_replace(" ", "\\ ", $s);
                $s = str_replace("\t", "\\\t", $s);
                $s = str_replace("(", "\\(", $s);
                $s = str_replace(")", "\\)", $s);
                return $s;
            }));

            return $env;
        });
    }

    public function rawDbCon(): SQLite3
    {
        return $this->lazy("rawDbCon", function () {
            return new SQLite3($this->config["sqlitedb"]);
        });
    }

    public function db(): SQLite3
    {
        return $this->lazy("db", function () {
            $db = $this->rawDbCon();
            $db->exec("PRAGMA foreign_keys = ON");

            (new Schema($db))->migrate();

            return $db;
        });
    }
}
