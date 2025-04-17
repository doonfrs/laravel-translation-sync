<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SyncTranslations extends Command
{
    protected $signature = 'translations:sync';
    protected $description = 'Scan and extract all translation keys into lang/ar.json';

    public function handle()
    {
        $langFiles = config('translation-sync.lang_files');
        if(empty($langFiles)){
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
                            $translationKeys[$key] = ''; // Default value is same as key
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
            $merged = array_merge($translationKeys, $existing); // existing translations will overwrite new ones

            // Sort alphabetically
            ksort($merged);

            // Save back to ar.json
            File::put($langFile, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $this->info('✅ Translations extracted and written to lang/' . $langFile);
        }
    }
}
