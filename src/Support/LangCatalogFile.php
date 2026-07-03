<?php

namespace Trinavo\TranslationSync\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Reads and writes flat translation catalog files in JSON or PHP format,
 * detected by file extension.
 */
class LangCatalogFile
{
    /**
     * @return array<string, string>
     */
    public static function read(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        if (str_ends_with($path, '.php')) {
            $translations = File::getRequire($path);

            if (! is_array($translations)) {
                throw new RuntimeException("Translation file [{$path}] must return an array.");
            }

            return $translations;
        }

        $translations = json_decode(File::get($path), true);

        if (! is_array($translations)) {
            throw new RuntimeException("Translation file [{$path}] contains invalid JSON.");
        }

        return $translations;
    }

    /**
     * @param  array<string, string>  $translations
     */
    public static function write(string $path, array $translations): void
    {
        ksort($translations, SORT_STRING);

        if (str_ends_with($path, '.php')) {
            $lines = "<?php\n\nreturn [\n";

            foreach ($translations as $key => $value) {
                $lines .= '    '.var_export((string) $key, true).' => '.var_export((string) $value, true).",\n";
            }

            $lines .= "];\n";

            File::put($path, $lines);

            return;
        }

        File::put($path, json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
