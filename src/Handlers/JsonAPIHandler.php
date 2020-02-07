<?php


namespace Micropoly\Handlers;


use Micropoly\Env;
use Micropoly\Handler;

abstract class JsonAPIHandler implements Handler
{
    abstract protected function handleAPIRequest(Env $env, array $variables): JsonAPIResult;

    public function handle(Env $env, array $variables)
    {
        $this->handleAPIRequest($env, $variables)->send();
    }
}