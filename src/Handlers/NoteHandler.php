<?php


namespace Micropoly\Handlers;


use Micropoly\Env;
use Micropoly\Handler;
use Micropoly\Models\Note;

class NoteHandler implements Handler
{
    public function handle(Env $env, array $variables)
    {
        $db = $env->db();

        $note = Note::byId($db, $variables["id"]);
        if ($note === null) {
            (new NotFoundHandler())->handle($env, []);
            return;
        }

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            if ($_POST["delete"] === "delete") {
                $note->delete($db);
                http_response_code(303);
                $url = $env->documentRoot();
                header("Location: {$url}");
                return;
            }

            $note->setContent($_POST["content"]);
            $note->setTags($_POST["tag"]);
            $note->save($db);
        }

        echo $env->twig()->render("/note.twig", ["note" => $note]);
    }
}