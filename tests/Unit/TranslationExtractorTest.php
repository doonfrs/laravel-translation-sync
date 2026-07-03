<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trinavo\TranslationSync\Services\TranslationExtractor;

class TranslationExtractorTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('textProvider')]
    public function test_extract_keys_from_text($text, $expected)
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
                ['simple.key'],
            ],
            // With parameters
            [
                "__('swap.request', ['item' => 'value'])",
                ['swap.request'],
            ],
            // Multiple translations
            [
                "__('first.key'); trans('second.key'); @lang('third.key')",
                ['first.key', 'second.key', 'third.key'],
            ],
            // Double quotes
            [
                'trans("double.quoted.key")',
                ['double.quoted.key'],
            ],
            // No matches
            [
                'echo "no translation here";',
                [],
            ],
            // Complex case with spaces
            [
                "__('complex.key' , [ 'foo' => 'bar' ])",
                ['complex.key'],
            ],
            // Nested function (should only match the translation key)
            [
                "__('nested.key', someFunction(__('not.captured')))",
                ['nested.key', 'not.captured'],
            ],
            // Escaped apostrophe in translation string
            [
                "{{ __('How Our Partnership Works - It\'s Simple!') }}",
                ["How Our Partnership Works - It's Simple!"],
            ],
            // Escaped double quote in translation string
            [
                '__("She said \"Hello\" to me")',
                ['She said "Hello" to me'],
            ],
            // Concatenated dynamic key must not leak its literal prefix
            [
                "__('payment_status.'.\$status)",
                [],
            ],
            // Concatenated dynamic key with spaces around the dot
            [
                "__('order_activity.field_' . \$field)",
                [],
            ],
            // Key ending with dots but properly closed is still extracted
            [
                "__('Loading...')",
                ['Loading...'],
            ],
            // trans_choice keys are extracted
            [
                "trans_choice(':count day remaining|:count days remaining', \$days)",
                [':count day remaining|:count days remaining'],
            ],
            // Namespaced keys are still extracted here (filtering happens later)
            [
                "trans('filament-users::user.resource.title.resource')",
                ['filament-users::user.resource.title.resource'],
            ],
        ];
    }
}
