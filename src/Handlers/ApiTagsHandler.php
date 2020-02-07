<?php


namespace Micropoly\Handlers;


use Micropoly\Env;
use Micropoly\Models\Tag;

class ApiTagsHandler extends JsonAPIHandler
{
    protected function handleAPIRequest(Env $env, array $variables): JsonAPIResult
    {
        return new JsonAPIResult(array_keys(Tag::getTagCounts($env->db())));
    }
}