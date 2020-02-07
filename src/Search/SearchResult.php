<?php


namespace Micropoly\Search;


use LogicException;
use Micropoly\DbQuery;
use Micropoly\Esc;
use Micropoly\Models\Note;
use SQLite3;

class SearchResult
{
    private Note $note;
    private array $highlights = [];

    private function __construct(Note $note, array $highlights)
    {
        $this->note = $note;
        $this->highlights = $highlights;
    }

    /**
     * @param SQLite3 $db
     * @param SearchExpr $expr
     * @return self[]
     */
    public static function search(SQLite3 $db, SearchExpr $expr): array
    {
        return $expr->countFTSQueries() === 1
            ? self::searchFTS($db, $expr)
            : self::searchComplex($db, $expr);
    }

    private static function searchComplex(SQLite3 $db, SearchExpr $expr): array
    {
        $sqlSearchExpr = $expr->toSQL("", false);

        $query = new DbQuery("
            SELECT
                n.id
            FROM notes n
            INNER JOIN note_contents nc
                ON nc.rowid = n.content_row
            WHERE {$sqlSearchExpr->sql}
        ");

        foreach ($sqlSearchExpr->bindings as $k => $v)
            $query->bind($k, $v);

        $ids = array_map(fn ($row) => $row[0], $query->fetchRows($db));
        $notes = Note::byIds($db, $ids);
        return array_map(fn ($note) => new self($note, []), $notes);
    }

    private static function highlightRangeContains(array $range, int $point): bool
    {
        [$start, $end] = $range;
        return $start <= $point && $point <= $end;
    }

    private static function areHighlightsOverlapping(array $a, array $b): bool
    {
        [$aStart, $aEnd] = $a;
        [$bStart, $bEnd] = $b;

        return self::highlightRangeContains($a, $bStart)
            || self::highlightRangeContains($a, $bEnd)
            || self::highlightRangeContains($b, $aStart)
            || self::highlightRangeContains($b, $aEnd);
    }

    private static function parseOffsetsToHighlights(string $offsets): array
    {
        $offsets = explode(" ", $offsets);
        $offsets = array_map("intval", $offsets);

        $phraseMatches = count($offsets) / 4;

        $highlights = [];
        for ($i = 0; $i < $phraseMatches; $i++) {
            $off = $offsets[$i * 4 + 2];
            $len = $offsets[$i * 4 + 3];

            if ($off < 0 || $len === 0)
                continue;

            $highlights[] = [$off, $off+$len-1];
        }

        usort($highlights, fn ($a, $b) => ($a[0] <=> $b[0]) ?: ($b[1] <=> $a[1]));

        // merge overlapping areas
        for ($i = count($highlights)-1; $i >= 0; $i--) {
            for ($j = $i-1; $j >= 0; $j--) {
                if (self::areHighlightsOverlapping($highlights[$i], $highlights[$j])) {
                    [$iStart, $iEnd] = $highlights[$i];
                    [$jStart, $jEnd] = $highlights[$j];

                    $highlights[$j] = [min($iStart, $jStart), max($iEnd, $jEnd)];
                    unset($highlights[$i]);
                    break;
                }
            }
        }

        return array_merge($highlights); // array_merge here renumbers the keys
    }

    private static function searchFTS(SQLite3 $db, SearchExpr $expr)
    {
        $sqlSearchExpr = $expr->toSQL("", true);
        $query = new DbQuery("
            SELECT
                n.id,
                offsets(nc.note_contents) AS offsets
            FROM notes n
            INNER JOIN note_contents nc
                ON nc.rowid = n.content_row
            WHERE {$sqlSearchExpr->sql}
        ");
        foreach ($sqlSearchExpr->bindings as $k => $v)
            $query->bind($k, $v);


        $offsets = $query->fetchIndexedValues($db, "offsets", "id");

        $notes = Note::byIds($db, array_keys($offsets));

        $out = [];
        foreach ($offsets as $id => $offString) {
            if (!isset($notes[$id]))
                throw new LogicException("Note '{$id}' not loaded but found?");

            $out[] = new self($notes[$id], self::parseOffsetsToHighlights($offString));
        }

        return $out;
    }

    public function renderHighlightedContent(): string
    {
        $out = "";
        $content = $this->note->getContent();
        $lastOff = 0;
        foreach ($this->highlights as [$start, $end]) {
            $out .= Esc::e(substr($content, $lastOff, $start - $lastOff), Esc::HTML_WITH_BR);
            $out .= '<b>' . Esc::e(substr($content, $start, $end - $start + 1), Esc::HTML_WITH_BR) . '</b>';

            $lastOff = $end + 1;
        }

        $out .= Esc::e(substr($content, $lastOff), Esc::HTML_WITH_BR);

        return $out;
    }

    public function getNote(): Note { return $this->note; }
}