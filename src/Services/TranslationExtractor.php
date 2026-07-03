<?php

namespace Trinavo\TranslationSync\Services;

use Illuminate\Support\Facades\File;

class TranslationExtractor
{
    /**
     * Extract translation keys from a given text.
     *
     * The closing quote must be followed by a comma or closing parenthesis so
     * that concatenated calls like __('payment_status.'.$value) do not leak
     * their literal prefix as a key.
     */
    public static function extractKeysFromText(string $text): array
    {
        $keys = [];

        // Match single-quoted strings with escaped quotes
        preg_match_all('/(?:__|trans_choice|trans|@lang)\s*\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*[,)]/s', $text, $singleQuoted);

        // Match double-quoted strings with escaped quotes
        preg_match_all('/(?:__|trans_choice|trans|@lang)\s*\(\s*"((?:[^"\\\\]|\\\\.)*)"\s*[,)]/s', $text, $doubleQuoted);

        // Combine results and unescape
        foreach ($singleQuoted[1] ?? [] as $match) {
            $keys[] = stripcslashes($match);
        }

        foreach ($doubleQuoted[1] ?? [] as $match) {
            $keys[] = stripcslashes($match);
        }

        return $keys;
    }

    /**
     * Scan directories for translation keys.
     *
     * @param  array<int, string>  $paths
     * @return array<string, string> Extracted keys as array keys (values are '')
     */
    public static function extractKeysFromPaths(array $paths): array
    {
        $translationKeys = [];

        foreach ($paths as $dir) {
            foreach (File::allFiles($dir) as $file) {
                if (! in_array($file->getExtension(), ['php', 'blade.php', 'vue'])) {
                    continue;
                }

                foreach (self::extractKeysFromText($file->getContents()) as $key) {
                    $translationKeys[stripslashes($key)] = '';
                }
            }
        }

        return $translationKeys;
    }
}
