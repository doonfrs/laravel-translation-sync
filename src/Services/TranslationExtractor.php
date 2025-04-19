<?php

namespace Trinavo\TranslationSync\Services;

class TranslationExtractor
{
    /**
     * Extract translation keys from a given text.
     *
     * @param string $text
     * @return array
     */
    public static function extractKeysFromText(string $text): array
    {
        $matches = [];
        preg_match_all('/(?:__|trans|@lang)\s*\(\s*([\'\"])(.*?)\1/s', $text, $matches);
        return $matches[2] ?? [];
    }
} 