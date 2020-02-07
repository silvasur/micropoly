<?php


namespace Micropoly\Tools;


use Micropoly\Entrypoint;
use Micropoly\Env;
use Micropoly\Models\Note;
use SQLite3;

class PopulateDevDb implements Entrypoint
{
    private const NUM_NOTES = 1000;
    private const WORDS_FILE = "/usr/share/dict/cracklib-small";
    private const TAGS_MIN_RAND = 0;
    private const TAGS_MAX_RAND = 6;
    private const CHANCE_TRASH = 0.1;
    private const CHANCE_INBOX = 0.4;
    private const CONTENT_MIN_WORDS = 3;
    private const CONTENT_MAX_WORDS = 200;

    private array $words = [];

    private function readWords()
    {
        $words = file_get_contents(self::WORDS_FILE);
        $words = explode("\n", $words);
        $words = array_map("trim", $words);
        $words = array_filter($words);

        $this->words = $words;
    }

    public function run(Env $env)
    {
        $this->readWords();

        $db = $env->db();
        for ($i = 0; $i < self::NUM_NOTES; $i++)
            $this->createTestNote($db);
    }

    private function randomWords(int $min, int $max): array
    {
        $words = [];
        $num = mt_rand($min, $max);
        for ($i = 0; $i < $num; $i++)
            $words[] = $this->words[mt_rand(0, count($this->words)-1)];

        return $words;
    }

    private static function byChance(float $chance): bool
    {
        return mt_rand() / mt_getrandmax() <= $chance;
    }

    private function createTestNote(SQLite3 $db): void
    {
        $note = new Note();
        $tags = $this->randomWords(self::TAGS_MIN_RAND, self::TAGS_MAX_RAND);
        if (self::byChance(self::CHANCE_INBOX))
            $tags[] = "inbox";
        $note->setTags($tags);
        $note->setContent(implode(" ", $this->randomWords(self::CONTENT_MIN_WORDS, self::CONTENT_MAX_WORDS)));
        $note->setTrash(self::byChance(self::CHANCE_TRASH));

        $note->save($db);
    }
}