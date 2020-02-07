<?php

namespace Micropoly;

use Micropoly\Env;

interface Handler
{
    public function handle(Env $env, array $variables);
}
