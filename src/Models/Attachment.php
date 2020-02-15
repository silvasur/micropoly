<?php


namespace Micropoly\Models;


use InvalidArgumentException;
use Micropoly\BoundVal;
use Micropoly\DbQuery;
use RuntimeException;
use SQLite3;

class Attachment
{
    public const HASH_ALGO = "sha3-256";

    private const HASH_PREFIX_LEN = 2;

    private string $id;
    private string $noteId;
    private string $hash;
    private ?string $fileName;
    private string $mime;

    private function __construct() {}

    /**
     * @param string $hash
     * @return string[]
     */
    private static function splitHash(string $hash): array
    {
        if (strlen($hash) <= self::HASH_PREFIX_LEN+1) { // +1 so $tail won't be empty
            throw new InvalidArgumentException("Invalid hash '$hash' given");
        }

        $head = substr($hash, 0, self::HASH_PREFIX_LEN);
        $tail = substr($hash, self::HASH_PREFIX_LEN);
        return [$head, $tail];
    }

    private static function relativeFilePathFromHash(string $hash): string
    {
        [$head, $tail] = self::splitHash($hash);

        return $head . DIRECTORY_SEPARATOR . $tail;
    }

    /**
     * @param string $attachmentPath
     * @param string $hash
     * @return string
     */
    private static function fullFilePathFromHash(string $attachmentPath, string $hash): string
    {
        return $attachmentPath . DIRECTORY_SEPARATOR . self::relativeFilePathFromHash($hash);
    }

    private static function deleteFileByHash(string $attachmentPath, string $hash): void
    {
        @unlink(self::fullFilePathFromHash($attachmentPath, $hash));
    }

    public function clearAbandoned(SQLite3 $db, string $attachmentPath): void
    {
        $db->exec("BEGIN");
        foreach ((new DbQuery("
            SELECT a.hash
            FROM attachments a
            LEFT JOIN note_attachments na
                ON na.hash = a.hash
            WHERE na.id IS NULL
        "))->fetchValues($db) as $hash) {
            self::deleteFileByHash($attachmentPath, $hash);
        }

        $db->exec("
            DELETE FROM attachments
            WHERE hash NOT IN (
                SELECT hash
                FROM note_attachments
            )
        ");
        $db->exec("COMMIT");
    }

    private static function fromRow(array $row): self
    {
        $out = new self();

        $out->id = $row["id"];
        $out->noteId = $row["note_id"];
        $out->hash = $row["hash"];
        $out->fileName = $row["file_name"];
        $out->mime = $row["mime"];

        return $out;
    }

    /**
     * @param SQLite3 $db
     * @param DbQuery $query
     * @return self[] Indexed by id
     */
    private static function byQuery(SQLite3 $db, DbQuery $query): array
    {
        return array_map([self::class, "fromRow"], $query->fetchIndexedRows($db, "id"));
    }

    /**
     * @param SQLite3 $db
     * @param string[] $ids
     * @return self[] Indexed by id
     */
    public static function byIds(SQLite3 $db, array $ids): array
    {
        $ids = array_map("trim", $ids);
        $ids = array_filter($ids);

        if (empty($ids))
            return [];

        return self::byQuery(
            $db,
            (new DbQuery("
                SELECT id, note_id, hash, file_name, mime
                FROM note_attachments
                WHERE id IN (" . DbQuery::valueListPlaceholders($ids) . ")
            "))
                ->bindMultiText($ids)
        );
    }

    public static function byId(SQLite3 $db, string $id): ?self
    {
        return self::byIds($db, [$id])[$id] ?? null;
    }

    /**
     * @param SQLite3 $db
     * @param string $noteId
     * @return self[] Indexed by id
     */
    public static function byNoteId(SQLite3 $db, string $noteId): array
    {
        return self::byQuery(
            $db,
            (new DbQuery("
                SELECT id, note_id, hash, file_name, mime
                FROM note_attachments
                WHERE note_id = ?
            "))
                ->bind(1, BoundVal::ofText($noteId))
        );
    }

    private static function transposeUploadsArray(array $uploads): array
    {
        $out = [];
        foreach ($uploads as $key => $values) {
            if (!is_array($values))
                $values = [$values];

            foreach ($values as $i => $v)
                $out[$i][$key] = $v;
        }

        return $out;
    }

    private static function hasHash(SQLite3 $db, string $hash): bool
    {
        return (new DbQuery("SELECT COUNT(*) FROM attachments WHERE hash = ?"))
                ->bind(1, BoundVal::ofText($hash))
                ->fetchRow($db)[0] > 0;
    }

    private static function mkUploadDir(string $attachmentPath, string $hash): void
    {
        [$head] = self::splitHash($hash);
        $dir = $attachmentPath . DIRECTORY_SEPARATOR . $head;

        if (!is_dir($dir))
            if (!mkdir($dir))
                throw new RuntimeException("Failed creating upload dir '$dir'");
    }

    /**
     * @param SQLite3 $db
     * @param string $attachmentPath
     * @param Note $note
     * @param array $uploads a $_FILES[$name] like array.
     *                       Can be populated by multiple files, like
     *                       {@see https://www.php.net/manual/en/features.file-upload.multiple.php} describes it.
     * @return self[]
     */
    public static function createFromUploads(SQLite3 $db, string $attachmentPath, Note $note, array $uploads): array
    {
        $out = [];
        $inserts = [];

        foreach (self::transposeUploadsArray($uploads) as $upload) {
            if ($upload["error"] !== UPLOAD_ERR_OK)
                continue;

            $hash = hash_file(self::HASH_ALGO, $upload["tmp_name"]);
            if (self::hasHash($db, $hash)) {
                unlink($upload["tmp_name"]);
            } else {
                self::mkUploadDir($attachmentPath, $hash);
                if (!move_uploaded_file($upload["tmp_name"], self::fullFilePathFromHash($attachmentPath, $hash))) {
                    throw new RuntimeException("Failed uploading file '{$upload["tmp_name"]}', original name was: '{$upload["name"]}'");
                }
                DbQuery::insertKV($db, "attachments", ["hash" => BoundVal::ofText($hash)]);
            }

            $obj = new self();

            $obj->id = uniqid("", true);
            $obj->noteId = $note->getId();
            $obj->hash = $hash;
            $obj->fileName = $upload["name"] ?? null;
            $obj->mime = (string)($upload["type"] ?? "application/octet-stream");

            $out[] = $obj;
            $inserts[] = $obj->buildInsertValues();
        }

        DbQuery::insert($db, "note_attachments", ["id", "note_id", "hash", "file_name", "mime"], $inserts);

        return $out;
    }

    private function buildInsertValues()
    {
        return [
            BoundVal::ofText($this->id),
            BoundVal::ofText($this->noteId),
            BoundVal::ofText($this->hash),
            $this->fileName === null ? BoundVal::ofNull() : BoundVal::ofText($this->fileName),
            BoundVal::ofText($this->mime),
        ];
    }

    public function delete(SQLite3 $db, string $attachmentPath): void
    {
        (new DbQuery("DELETE FROM note_attachments WHERE id = ?"))->bind(1, BoundVal::ofText($this->id))->exec($db);
        self::clearAbandoned($db, $attachmentPath);
    }

    public function getId(): string { return $this->id; }
    public function getNoteId(): string { return $this->noteId; }
    public function getHash(): string { return $this->hash; }
    public function getFileName(): ?string { return $this->fileName; }
    public function getMime(): string { return $this->mime; }

    public function getFilePath(string $attachmentPath): string
    {
        return self::fullFilePathFromHash($attachmentPath, $this->hash);
    }
}