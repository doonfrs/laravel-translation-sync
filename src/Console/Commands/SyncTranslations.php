<?php

namespace Trinavo\TranslationSync\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Trinavo\TranslationSync\Services\TranslationExtractor;

class SyncTranslations extends Command
{
    protected $signature = 'translations:sync';
    protected $description = 'Scan and extract all translation keys into lang/ar.json';

    public function handle()
    {
        $langFiles = config('translation-sync.lang_files');
        if (empty($langFiles)) {
            $this->error('No lang files found in config/translation-sync.php');
            return;
        }
        $scanPaths = config('translation-sync.scan_paths');
        if (empty($scanPaths)) {
            $scanPaths = [base_path('app'), base_path('resources'), base_path('config')];
        }
        $translationKeys = [];

        foreach ($scanPaths as $dir) {
            $files = File::allFiles($dir);
            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['php', 'blade.php', 'vue'])) {
                    $contents = $file->getContents();

                    // Use the TranslationExtractor service
                    $keys = TranslationExtractor::extractKeysFromText($contents);

                    if (!empty($keys)) {
                        foreach ($keys as $key) {
                            $unescapedKey = stripslashes($key);
                            $translationKeys[$unescapedKey] = '';
                        }
                    }
                }
            }
        }

        $removeUnusedKeys = config('translation-sync.remove_unused_keys', false);

        foreach ($langFiles as $langFile) {
            $existing = File::exists($langFile)
                ? json_decode(File::get($langFile), true)
                : [];

            // Create a copy of translationKeys for this file to avoid modifying the original
            $fileTranslationKeys = array_diff_key($translationKeys, $existing);

            if ($removeUnusedKeys) {
                // Only keep existing keys that are still found in the scanned files
                $filtered = array_intersect_key($existing, $translationKeys);
                $merged = array_merge($filtered, $fileTranslationKeys);
            } else {
                $merged = array_merge($existing, $fileTranslationKeys);
            }

            File::put($langFile, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $langFileSimplePath = ltrim(str_replace(base_path(), '', $langFile), '/');

            $this->info('✅ Translations extracted and written to ' . $langFileSimplePath);
        }
    }
}
