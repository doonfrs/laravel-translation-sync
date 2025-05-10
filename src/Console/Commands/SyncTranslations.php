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

        foreach ($langFiles as $langFile) {
            $existing = File::exists($langFile)
                ? json_decode(File::get($langFile), true)
                : [];

            $translationKeys = array_diff_key($translationKeys, $existing);

            $merged = array_merge($existing, $translationKeys);

            File::put($langFile, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $langFileSimplePath = ltrim(str_replace(base_path(), '', $langFile), '/');

            $this->info('✅ Translations extracted and written to ' . $langFileSimplePath);
        }
    }
}
