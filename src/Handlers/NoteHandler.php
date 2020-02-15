<?php


namespace Micropoly\Handlers;


use Micropoly\Env;
use Micropoly\Handler;
use Micropoly\Models\Attachment;
use Micropoly\Models\Note;
use Micropoly\TemplateModelWrappers\NoteForTemplate;

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
            if (isset($_POST["delete"]) && $_POST["delete"] === "delete") {
                $note->delete($db);
                http_response_code(303);
                $url = $env->documentRoot();
                header("Location: {$url}");
                return;
            }

            $note->setContent($_POST["content"]);
            $note->setTags($_POST["tag"]);
            $note->save($db);

            $deleteAttachments = $_POST['attachment_delete'] ?? [];
            $deleteAttachments = array_filter($deleteAttachments, fn ($ok) => (bool)(int)$ok);
            $deleteAttachments = array_keys($deleteAttachments);
            $deleteAttachments = Attachment::byIds($db, $deleteAttachments);
            $deleteAttachments = array_filter($deleteAttachments, fn (Attachment $att) => $att->getNoteId() === $note->getId());

            /** @var Attachment $att */
            foreach ($deleteAttachments as $att)
                $att->delete($db, $env->attachmentsPath());

            if (isset($_FILES['attachments']))
                Attachment::createFromUploads($env->db(), $env->attachmentsPath(), $note, $_FILES['attachments']);
        }

        echo $env->twig()->render("/note.twig", ["note" => new NoteForTemplate($db, $note)]);
    }
}