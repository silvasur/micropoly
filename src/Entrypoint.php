<?php


namespace Micropoly;


interface Entrypoint
{
    public function run(Env $env);
}