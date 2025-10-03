<?php

namespace Trinavo\TranslationSync\Services;

class TranslationExtractor
{
    /**
     * Extract translation keys from a given text.
     */
    public static function extractKeysFromText(string $text): array
    {
        $keys = [];

        // Match single-quoted strings with escaped quotes
        preg_match_all('/(?:__|trans|@lang)\s*\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/s', $text, $singleQuoted);

        // Match double-quoted strings with escaped quotes
        preg_match_all('/(?:__|trans|@lang)\s*\(\s*"((?:[^"\\\\]|\\\\.)*)"/s', $text, $doubleQuoted);

        // Combine results and unescape
        foreach ($singleQuoted[1] ?? [] as $match) {
            $keys[] = stripcslashes($match);
        }

        foreach ($doubleQuoted[1] ?? [] as $match) {
            $keys[] = stripcslashes($match);
        }

        return $keys;
    }
}
