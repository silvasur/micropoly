<?php


namespace Micropoly\Handlers;


use Micropoly\Env;
use Micropoly\Esc;
use Micropoly\Handler;
use Micropoly\Models\Attachment;
use Micropoly\Models\Note;

class NewNote implements Handler
{
    private static function getPostedContent(): ?string
    {
        if (empty($_POST["content"]))
            return null;

        $content = trim((string)$_POST["content"]);
        return empty($content) ? null : $content;
    }

    public function handle(Env $env, array $variables)
    {
        $content = self::getPostedContent();
        $templateData = [];
        if ($content !== null) {
            $note = new Note();
            $note->setContent($content);
            $note->setTags($_POST["tag"]);
            $note->save($env->db());

            if (isset($_FILES['attachments']))
                Attachment::createFromUploads($env->db(), $env->attachmentsPath(), $note, $_FILES['attachments']);

            $url = $env->documentRoot() . "n/" . $note->getId();
            if ($_POST["create_and_new"]) {
                $templateData["success"] = true;
            } else {
                http_response_code(303);
                header("Location: {$url}");
                echo 'Note created: <a href="' . Esc::e($url) . '">';
                return;
            }
        }

        echo $env->twig()->render("/new_note.twig", $templateData);
    }
}