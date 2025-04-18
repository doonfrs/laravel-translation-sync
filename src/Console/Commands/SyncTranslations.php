<?php

namespace Trinavo\TranslationSync\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

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
        $directories = [base_path('app'), base_path('resources')];
        $translationKeys = [];

        foreach ($directories as $dir) {
            $files = File::allFiles($dir);
            foreach ($files as $file) {
                if (in_array($file->getExtension(), ['php', 'blade.php', 'vue'])) {
                    $contents = $file->getContents();

                    // Match __(), trans(), @lang()
                    preg_match_all("/(?:__|trans|@lang)\(['\"](.+?)['\"]\)/", $contents, $matches);

                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $key) {
                            $unescapedKey = stripslashes($key);
                            $translationKeys[$unescapedKey] = ''; // Default value is same as key
                        }
                    }
                }
            }
        }

        foreach ($langFiles as $langFile) {
            $existing = File::exists($langFile)
                ? json_decode(File::get($langFile), true)
                : [];

            // Merge and preserve existing
            $merged = array_merge($translationKeys, $existing);

            // Sort so that untranslated (empty value) keys are at the end
            uksort($merged, function($a, $b) use ($merged) {
                $aEmpty = $merged[$a] === '';
                $bEmpty = $merged[$b] === '';
                if ($aEmpty === $bEmpty) {
                    return strcmp($a, $b); // sort alphabetically within group
                }
                return $aEmpty ? 1 : -1; // empty values go last
            });

            File::put($langFile, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->info('✅ Translations extracted and written to lang/' . $langFile);
        }
    }
}
