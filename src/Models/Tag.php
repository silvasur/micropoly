<?php


namespace Micropoly\Models;


use Micropoly\DbQuery;

class Tag
{
    private const TAGCLOUD_MAGNITUDES = 5;

    /**
     * Calculates a tag cloud array to be used as an input to the tagcloud macro
     * @param array $tagCounts [string tag => int count]
     * @return array
     */
    public static function calcTagCloud(array $tagCounts): array
    {
        $tagCounts = array_map("intval", $tagCounts);
        $tagCounts = array_filter($tagCounts, fn ($count) => $count !== 0);

        if (empty($tagCounts))
            return [];

        $maxCount = max(array_values($tagCounts));
        $tagCounts = array_map(fn ($count) => floor($count / ($maxCount+1) * self::TAGCLOUD_MAGNITUDES) + 1, $tagCounts);
        ksort($tagCounts);
        return $tagCounts;
    }

    public static function getTagCounts(\SQLite3 $db): array
    {
        return (new DbQuery("SELECT tag, num FROM tagcloud"))
            ->fetchIndexedValues($db, "num", "tag");
    }
}