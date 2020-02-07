<?php

namespace Micropoly\Handlers;

use Micropoly\Env;
use Micropoly\Handler;
use Micropoly\Models\Tag;

class Index implements Handler
{

    public function handle(Env $env, array $variables)
    {
        echo $env->twig()->render("/index.twig", [
            "title" => "hello",
            "msg" => "Johoo <script>alert(1)</script>",
            "tagcloud" => Tag::calcTagCloud(Tag::getTagCounts($env->db())),
        ]);
    }
}
