<?php


namespace Micropoly\TemplateModelWrappers;


use Micropoly\Models\Note;
use SQLite3;

class NoteForTemplate
{
    private SQLite3 $db;
    private Note $note;

    /**
     * NoteForModel constructor.
     * @param SQLite3 $db
     * @param Note $note
     */
    public function __construct(SQLite3 $db, Note $note)
    {
        $this->db = $db;
        $this->note = $note;
    }

    /**
     * @param SQLite3 $db
     * @param Note[] $notes
     * @return self[]
     */
    public static function wrapMany(SQLite3 $db, array $notes): array
    {
        return array_map(static fn(Note $note) => new self($db, $note), $notes);
    }

    public function getId(): string { return $this->note->getId(); }
    public function getContent(): string { return $this->note->getContent(); }
    public function getTags(): array { return $this->note->getTags(); }

    public function getAttachments(): array
    {
        return $this->note->getAttachments($this->db);
    }
}