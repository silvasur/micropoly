<?php

namespace Micropoly;

use SQLite3;

class Schema
{
    private SQLite3 $db;

    /**
     * @param SQLite3 $db
     */
    public function __construct(SQLite3 $db) { $this->db = $db; }

    private function getSchemaVersion(): int
    {
        $n = $this->db->querySingle("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'schema_meta'");
        if ($n !== "schema_meta")
            return 0;

        return (int)$this->db->querySingle("SELECT value FROM schema_meta WHERE key = 'version'");
    }

    private function setSchemaVersion(int $v): void
    {
        (new DbQuery("REPLACE INTO schema_meta (key, value) VALUES ('version', :v)"))
            ->bind(":v", $v)
            ->exec($this->db);
    }

    public function migrate()
    {
        $version = $this->getSchemaVersion();

        switch ($version) {
            case 0:
                $this->v1();
                $this->setSchemaVersion(1);
        }
    }

    private function v1()
    {
        $this->db->exec("
            CREATE TABLE schema_meta (
                key VARCHAR(100) NOT NULL PRIMARY KEY,
                value
            ) WITHOUT ROWID
        ");
        $this->db->exec("
            CREATE VIRTUAL TABLE note_contents USING fts4 (content TEXT)
        ");
        $this->db->exec("
            CREATE TABLE notes (
                id VARCHAR(23) NOT NULL PRIMARY KEY,
                content_row INT NOT NULL,
                created_at BIGINT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                changed_at BIGINT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                trash INT NOT NULL DEFAULT 0
            ) WITHOUT ROWID
        ");
        $this->db->exec("
            CREATE TABLE tags (
                note_id VARCHAR(23) NOT NULL REFERENCES notes (id) ON UPDATE CASCADE ON DELETE CASCADE,
                tag TEXT NOT NULL,
                PRIMARY KEY (note_id, tag)
            ) WITHOUT ROWID
        ");
        $this->db->exec("CREATE INDEX tag ON tags (tag)");
        $this->db->exec("
            CREATE VIEW tagcloud AS
                SELECT
                    tag,
                    COUNT(*) AS num
                FROM tags
                GROUP BY tag
        ");
    }
}
