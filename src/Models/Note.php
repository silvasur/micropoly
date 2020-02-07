<?php


namespace Micropoly\Models;


use Micropoly\BoundVal;
use Micropoly\DbQuery;
use Micropoly\Search\SearchExpr;
use SQLite3;

class Note
{
    private bool $savedToDb = false;
    private string $id;
    private string $content;
    private array $tags = [];
    private bool $trash = false;

    /**
     * Note constructor.
     */
    public function __construct()
    {
        $this->id = uniqid("", true);
    }

    /**
     * @param SQLite3 $db
     * @param DbQuery $query
     * @return self[]
     */
    private static function fromQuery(SQLite3 $db, DbQuery $query): array
    {
        $out = [];

        foreach ($query->fetchRowsAssoc($db) as $row) {
            $note = new self();

            $note->savedToDb = true;
            $note->id = $row["id"];
            $note->content = $row["content"];
            $note->trash = (bool)(int)$row["trash"];

            $out[$row["id"]] = $note;
        }

        if (!empty($out)) {
            $q = (new DbQuery("SELECT tag, note_id FROM tags WHERE note_id IN (" . DbQuery::valueListPlaceholders($out) . ")"))
                ->bindMultiText(array_keys($out));

            foreach ($q->fetchRows($db) as [$tag, $id]) {
                $out[$id]->tags[] = $tag;
            }
        }

        return $out;
    }

    /**
     * @param SQLite3 $db
     * @param array $ids
     * @return self[] indexes by id
     */
    public static function byIds(SQLite3 $db, array $ids): array
    {
        if (empty($ids))
            return [];

        $query = (new DbQuery("
            SELECT note.id, content.content, note.trash
            FROM notes note
            INNER JOIN note_contents content
                ON content.rowid = note.content_row
            WHERE id IN (" . DbQuery::valueListPlaceholders($ids) . ")
        "))->bindMultiText($ids);

        return self::fromQuery($db, $query);
    }

    public static function byId(SQLite3 $db, string $id): ?self
    {
        return self::byIds($db, [$id])[$id] ?? null;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     * @return Note
     */
    public function setContent(string $content): Note
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param array $tags
     * @return Note
     */
    public function setTags(array $tags): Note
    {
        $tags = array_map("trim", $tags);
        $tags = array_filter($tags);
        $tags = array_unique($tags);

        $this->tags = $tags;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTrash(): bool
    {
        return $this->trash;
    }

    /**
     * @param bool $trash
     * @return Note
     */
    public function setTrash(bool $trash): Note
    {
        $this->trash = $trash;
        return $this;
    }

    private function deleteContent(SQLite3 $db)
    {
        (new DbQuery("DELETE FROM note_contents WHERE rowid IN (SELECT content_row FROM notes WHERE id = ?)"))
            ->bind(1, BoundVal::ofText($this->id))
            ->exec($db);
    }

    private function deleteTags(SQLite3 $db)
    {
        (new DbQuery("DELETE FROM tags WHERE note_id = ?"))
            ->bind(1, BoundVal::ofText($this->id))
            ->exec($db);
    }

    public function save(SQLite3 $db)
    {
        if ($this->savedToDb)
            $this->update($db);
        else
            $this->insert($db);
    }

    private function insert(SQLite3 $db)
    {
        $db->exec("BEGIN");

        $this->deleteContent($db);

        DbQuery::insertKV($db, "note_contents", ["content" => BoundVal::ofText($this->content)]);
        $rowid = (new DbQuery("SELECT last_insert_rowid()"))->fetchRow($db)[0];

        DbQuery::insertKV($db, "notes", [
            "id" => BoundVal::ofText($this->id),
            "content_row" => BoundVal::ofInt($rowid),
            "trash" => BoundVal::ofInt($this->trash ? 0 : 1),
        ]);

        $this->writeTags($db);

        $db->exec("COMMIT");
    }

    private function update(SQLite3 $db)
    {
        $db->exec("BEGIN");

        $this->deleteTags($db);

        (new DbQuery("
            UPDATE note_contents
            SET content = :content
            WHERE rowid = (
                SELECT content_row
                FROM notes
                WHERE id = :id
            )
        "))
            ->bind("content", BoundVal::ofText($this->content))
            ->bind("id", BoundVal::ofText($this->id))
            ->exec($db);

        $this->writeTags($db);

        (new DbQuery("
            UPDATE notes
            SET changed_at = CURRENT_TIMESTAMP,
                trash = :trash
            WHERE id = :id
        "))
            ->bind("id", BoundVal::ofText($this->id))
            ->bind("trash", BoundVal::ofInt($this->trash ? 0 : 1))
            ->exec($db);

        $db->exec("COMMIT");
    }

    /**
     * @param SQLite3 $db
     */
    private function writeTags(SQLite3 $db): void
    {
        $this->deleteTags($db);
        DbQuery::insert($db,
            "tags",
            ["note_id", "tag"],
            array_map(fn($t) => [BoundVal::ofText($this->id), BoundVal::ofText($t)], $this->tags)
        );
    }

    public function delete(SQLite3 $db): void
    {
        $this->deleteTags($db);
        $this->deleteContent($db);
        (new DbQuery("DELETE FROM notes WHERE id = ?"))
            ->bind(1, BoundVal::ofText($this->id))
            ->exec($db);
        $this->savedToDb = false;
    }
}