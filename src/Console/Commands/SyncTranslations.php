<?php

namespace Trinavo\TranslationSync\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Trinavo\TranslationSync\Services\TranslationExtractor;
use Trinavo\TranslationSync\Support\LangCatalogFile;

class SyncTranslations extends Command
{
    protected $signature = 'translations:sync';

    protected $description = 'Scan and extract all translation keys into the configured lang catalogs (JSON or PHP)';

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

        $translationKeys = TranslationExtractor::extractKeysFromPaths($scanPaths);

        // Never add vendor-namespaced keys (pkg::key): an empty value in the
        // flat catalog would mask the vendor's own translation and render the
        // raw key instead.
        $translationKeys = array_filter(
            $translationKeys,
            fn (string $key) => ! Str::contains($key, '::'),
            ARRAY_FILTER_USE_KEY
        );

        $removeUnusedKeys = config('translation-sync.remove_unused_keys', false);

        foreach ($langFiles as $langFile) {
            $existing = LangCatalogFile::read($langFile);

            // Create a copy of translationKeys for this file to avoid modifying the original
            $fileTranslationKeys = array_diff_key($translationKeys, $existing);

            if ($removeUnusedKeys) {
                // Only keep existing keys that are still found in the scanned files
                $filtered = array_intersect_key($existing, $translationKeys);
                $merged = array_merge($filtered, $fileTranslationKeys);
            } else {
                $merged = array_merge($existing, $fileTranslationKeys);
            }

            LangCatalogFile::write($langFile, $merged);

            $langFileSimplePath = ltrim(str_replace(base_path(), '', $langFile), '/');

            $this->info('✅ Translations extracted and written to '.$langFileSimplePath);
        }
    }
}
