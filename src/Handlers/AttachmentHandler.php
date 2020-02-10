<?php


namespace Micropoly\Handlers;


use Micropoly\Env;
use Micropoly\Handler;
use Micropoly\Models\Attachment;

class AttachmentHandler implements Handler
{
    public function handle(\Micropoly\Env $env, array $variables)
    {
        $db = $env->db();

        $attachment = Attachment::byId($db, $variables["id"]);
        if ($attachment === null) {
            (new NotFoundHandler())->handle($env, []);
            return;
        }

        header("Content-Type: {$attachment->getMime()}");
        readfile($attachment->getFilePath($env->attachmentsPath()));
    }
}