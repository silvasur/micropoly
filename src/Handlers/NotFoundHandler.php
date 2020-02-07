<?php

namespace Micropoly\Handlers;

use Micropoly\Env;
use Micropoly\Handler;

class NotFoundHandler implements Handler
{
    public function handle(Env $env, array $variables)
    {
        http_response_code(404);
        echo "404";
    }
}
