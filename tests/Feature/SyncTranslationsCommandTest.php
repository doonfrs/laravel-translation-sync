<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;

class SyncTranslationsCommandTest extends TestCase
{
    protected string $testLangFile;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure a clean environment
        $this->testLangFile = base_path('lang/test.json');
        if (file_exists($this->testLangFile)) {
            unlink($this->testLangFile);
        }
        // Fake config
        config(['translation-sync.lang_files' => [$this->testLangFile]]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLangFile)) {
            unlink($this->testLangFile);
        }
        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [\Trinavo\TranslationSync\Providers\TranslationSyncServiceProvider::class];
    }

    public function test_command_creates_translation_file_with_keys()
    {
        // Create a dummy PHP file with translation calls
        $appDir = base_path('app');
        if (! is_dir($appDir)) {
            mkdir($appDir, 0777, true);
        }
        $dummyFile = $appDir.'/DummyForTranslationTest.php';
        file_put_contents($dummyFile, "<?php\n"
            ."__ ('test.key1');\n"
            ."trans('test.key2');\n"
            ."@lang(\"test.key3\");\n"
            ."__('test.key4', ['foo' => 'bar']);\n"
            ."trans( 'test.key5' , [ 'bar' => 'baz' ] );\n"
            ."__('test.key6', someFunction(__('test.key7')));\n"
            ."echo 'not a translation';\n"
            ."'text' => __('You have received a new swap request for your item: \":item\"', ['item' => 'dummy']),\n"
        );

        // Run the command
        $this->artisan('translations:sync')->assertExitCode(0);

        // Assert the lang file was created and contains the keys
        $this->assertFileExists($this->testLangFile);
        $json = json_decode(file_get_contents($this->testLangFile), true);
        $this->assertArrayHasKey('test.key1', $json);
        $this->assertArrayHasKey('test.key2', $json);
        $this->assertArrayHasKey('test.key3', $json);
        $this->assertArrayHasKey('test.key4', $json);
        $this->assertArrayHasKey('test.key5', $json);
        $this->assertArrayHasKey('test.key6', $json);
        $this->assertArrayHasKey('test.key7', $json);
        $this->assertArrayHasKey('You have received a new swap request for your item: ":item"', $json);
        $this->assertEquals('', $json['test.key1']);
        $this->assertEquals('', $json['test.key2']);
        $this->assertEquals('', $json['test.key3']);
        $this->assertEquals('', $json['test.key4']);
        $this->assertEquals('', $json['test.key5']);
        $this->assertEquals('', $json['test.key6']);
        $this->assertEquals('', $json['test.key7']);
        $this->assertEquals('', $json['You have received a new swap request for your item: ":item"']);

        // Clean up
        unlink($dummyFile);
    }

    public function test_command_updates_multiple_language_files()
    {
        // Create test language files
        $langDir = base_path('lang');
        if (! is_dir($langDir)) {
            mkdir($langDir, 0777, true);
        }

        $arFile = $langDir.'/ar.json';
        $trFile = $langDir.'/tr.json';

        // Create initial content for both files
        $initialAr = ['existing.key' => 'existing value'];
        $initialTr = ['existing.key' => 'mevcut değer'];

        file_put_contents($arFile, json_encode($initialAr, JSON_PRETTY_PRINT));
        file_put_contents($trFile, json_encode($initialTr, JSON_PRETTY_PRINT));

        // Configure multiple language files
        config(['translation-sync.lang_files' => [$arFile, $trFile]]);

        // Create a dummy PHP file with new translation calls
        $appDir = base_path('app');
        if (! is_dir($appDir)) {
            mkdir($appDir, 0777, true);
        }
        $dummyFile = $appDir.'/DummyForMultipleFilesTest.php';
        file_put_contents($dummyFile, "<?php\n"
            ."__('new.key1');\n"
            ."trans('new.key2');\n"
            ."__('new.key3');\n"
        );

        // Run the command
        $this->artisan('translations:sync')->assertExitCode(0);

        // Assert both files were updated
        $this->assertFileExists($arFile);
        $this->assertFileExists($trFile);

        // Check ar.json content
        $arJson = json_decode(file_get_contents($arFile), true);
        $this->assertArrayHasKey('existing.key', $arJson);
        $this->assertEquals('existing value', $arJson['existing.key']);
        $this->assertArrayHasKey('new.key1', $arJson);
        $this->assertArrayHasKey('new.key2', $arJson);
        $this->assertArrayHasKey('new.key3', $arJson);
        $this->assertEquals('', $arJson['new.key1']);
        $this->assertEquals('', $arJson['new.key2']);
        $this->assertEquals('', $arJson['new.key3']);

        // Check tr.json content
        $trJson = json_decode(file_get_contents($trFile), true);
        $this->assertArrayHasKey('existing.key', $trJson);
        $this->assertEquals('mevcut değer', $trJson['existing.key']);
        $this->assertArrayHasKey('new.key1', $trJson);
        $this->assertArrayHasKey('new.key2', $trJson);
        $this->assertArrayHasKey('new.key3', $trJson);
        $this->assertEquals('', $trJson['new.key1']);
        $this->assertEquals('', $trJson['new.key2']);
        $this->assertEquals('', $trJson['new.key3']);

        // Clean up
        unlink($dummyFile);
        unlink($arFile);
        unlink($trFile);
    }

    public function test_command_removes_unused_keys_when_configured()
    {
        // Create a dummy PHP file with translation calls
        $appDir = base_path('app');
        if (! is_dir($appDir)) {
            mkdir($appDir, 0777, true);
        }
        $dummyFile = $appDir.'/DummyForRemovalTest.php';
        file_put_contents($dummyFile, "<?php\n"
            ."__('current.key1');\n"
            ."trans('current.key2');\n"
        );

        // Create initial lang file with both used and unused keys
        $langDir = base_path('lang');
        if (! is_dir($langDir)) {
            mkdir($langDir, 0777, true);
        }
        $langFile = $langDir.'/removal_test.json';
        $initialContent = [
            'current.key1' => 'Used key 1',
            'current.key2' => 'Used key 2',
            'unused.key1' => 'This should be removed',
            'unused.key2' => 'This should also be removed',
        ];
        file_put_contents($langFile, json_encode($initialContent, JSON_PRETTY_PRINT));

        // Configure the command with removal enabled
        config([
            'translation-sync.lang_files' => [$langFile],
            'translation-sync.remove_unused_keys' => true,
        ]);

        // Run the command
        $this->artisan('translations:sync')->assertExitCode(0);

        // Assert the lang file exists and only contains used keys
        $this->assertFileExists($langFile);
        $json = json_decode(file_get_contents($langFile), true);

        // Should contain used keys
        $this->assertArrayHasKey('current.key1', $json);
        $this->assertArrayHasKey('current.key2', $json);
        $this->assertEquals('Used key 1', $json['current.key1']);
        $this->assertEquals('Used key 2', $json['current.key2']);

        // Should not contain unused keys
        $this->assertArrayNotHasKey('unused.key1', $json);
        $this->assertArrayNotHasKey('unused.key2', $json);

        // Clean up
        unlink($dummyFile);
        unlink($langFile);
    }

    public function test_command_keeps_unused_keys_when_not_configured()
    {
        // Create a dummy PHP file with translation calls
        $appDir = base_path('app');
        if (! is_dir($appDir)) {
            mkdir($appDir, 0777, true);
        }
        $dummyFile = $appDir.'/DummyForKeepTest.php';
        file_put_contents($dummyFile, "<?php\n"
            ."__('current.key1');\n"
            ."trans('current.key2');\n"
        );

        // Create initial lang file with both used and unused keys
        $langDir = base_path('lang');
        if (! is_dir($langDir)) {
            mkdir($langDir, 0777, true);
        }
        $langFile = $langDir.'/keep_test.json';
        $initialContent = [
            'current.key1' => 'Used key 1',
            'current.key2' => 'Used key 2',
            'unused.key1' => 'This should be kept',
            'unused.key2' => 'This should also be kept',
        ];
        file_put_contents($langFile, json_encode($initialContent, JSON_PRETTY_PRINT));

        // Configure the command with removal disabled (default)
        config([
            'translation-sync.lang_files' => [$langFile],
            'translation-sync.remove_unused_keys' => false,
        ]);

        // Run the command
        $this->artisan('translations:sync')->assertExitCode(0);

        // Assert the lang file exists and contains all keys
        $this->assertFileExists($langFile);
        $json = json_decode(file_get_contents($langFile), true);

        // Should contain used keys
        $this->assertArrayHasKey('current.key1', $json);
        $this->assertArrayHasKey('current.key2', $json);
        $this->assertEquals('Used key 1', $json['current.key1']);
        $this->assertEquals('Used key 2', $json['current.key2']);

        // Should still contain unused keys
        $this->assertArrayHasKey('unused.key1', $json);
        $this->assertArrayHasKey('unused.key2', $json);
        $this->assertEquals('This should be kept', $json['unused.key1']);
        $this->assertEquals('This should also be kept', $json['unused.key2']);

        // Clean up
        unlink($dummyFile);
        unlink($langFile);
    }
}
