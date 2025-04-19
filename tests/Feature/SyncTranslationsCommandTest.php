<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Trinavo\TranslationSync\Console\Commands\SyncTranslations;

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

    public function testCommandCreatesTranslationFileWithKeys()
    {
        // Create a dummy PHP file with translation calls
        $appDir = base_path('app');
        if (!is_dir($appDir)) {
            mkdir($appDir, 0777, true);
        }
        $dummyFile = $appDir . '/DummyForTranslationTest.php';
        file_put_contents($dummyFile, "<?php\n"
            . "__ ('test.key1');\n"
            . "trans('test.key2');\n"
            . "@lang(\"test.key3\");\n"
            . "__('test.key4', ['foo' => 'bar']);\n"
            . "trans( 'test.key5' , [ 'bar' => 'baz' ] );\n"
            . "__('test.key6', someFunction(__('test.key7')));\n"
            . "echo 'not a translation';\n"
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
        $this->assertEquals('', $json['test.key1']);
        $this->assertEquals('', $json['test.key2']);
        $this->assertEquals('', $json['test.key3']);
        $this->assertEquals('', $json['test.key4']);
        $this->assertEquals('', $json['test.key5']);
        $this->assertEquals('', $json['test.key6']);
        $this->assertEquals('', $json['test.key7']);

        // Clean up
        unlink($dummyFile);
    }
} 