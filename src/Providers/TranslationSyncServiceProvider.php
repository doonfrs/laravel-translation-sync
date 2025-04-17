<?php

namespace Trinavo\TranslationSync\Providers;

use App\Console\Commands\SyncTranslations;
use Illuminate\Support\ServiceProvider;

class TranslationSyncServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/translation-sync.php',
            'translation-sync'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/translation-sync.php' => config_path('translation-sync.php'),
        ], 'translation-sync-config');


        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncTranslations::class,
            ]);
        }
    }
}
