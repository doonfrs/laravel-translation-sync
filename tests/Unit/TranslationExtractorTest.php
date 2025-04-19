<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trinavo\TranslationSync\Services\TranslationExtractor;

class TranslationExtractorTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('textProvider')]
    public function testExtractKeysFromText($text, $expected)
    {
        $result = TranslationExtractor::extractKeysFromText($text);
        $this->assertEquals($expected, $result);
    }

    public static function textProvider()
    {
        return [
            // Simple case
            [
                "__('simple.key')",
                ['simple.key']
            ],
            // With parameters
            [
                "__('swap.request', ['item' => 'value'])",
                ['swap.request']
            ],
            // Multiple translations
            [
                "__('first.key'); trans('second.key'); @lang('third.key')",
                ['first.key', 'second.key', 'third.key']
            ],
            // Double quotes
            [
                'trans("double.quoted.key")',
                ['double.quoted.key']
            ],
            // No matches
            [
                'echo "no translation here";',
                []
            ],
            // Complex case with spaces
            [
                "__('complex.key' , [ 'foo' => 'bar' ])",
                ['complex.key']
            ],
            // Nested function (should only match the translation key)
            [
                "__('nested.key', someFunction(__('not.captured')))",
                ['nested.key', 'not.captured']
            ],
        ];
    }
} 