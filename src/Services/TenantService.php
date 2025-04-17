<?php

namespace Trinavo\MultiTenancy\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Trinavo\MultiTenancy\Exceptions\TenantFetchException;
use Trinavo\MultiTenancy\Exceptions\TenantNotFoundException;
use Trinavo\MultiTenancy\Models\TenantDTO;
use Trinavo\MultiTenancy\Notifications\TenantWelcomeNotification;

class TenantService
{
    /**
     * The current tenant slug.
     *
     * @var string|null
     */
    protected $currentTenantSlug = null;

    public function switchByCurrentHost()
    {
        if ($this->isMainDomain()) {
            return;
        }
        if ($this->currentTenantSlug) {
            return;
        }
        $domain = request()->getHost();
        $tenantInfo = $this->getTenantInfo($domain);
        
        $this->switchTo($tenantInfo->getSlug());
    }

    public function switchTo(string $slug, $tenantDomain = null)
    {
        if ($this->currentTenantSlug === $slug) {
            return;
        }

        Log::info('Switching to tenant: ' . $slug . ' from ' . $this->currentTenantSlug);
        $this->currentTenantSlug = $slug;

        $databasePath = $this->getDatabasePath($slug);
        Config::set('database.connections.sqlite.database', $databasePath);
        Config::set('database.default', 'sqlite');
        Config::set('world.connection', 'sqlite');
        Config::set('cache.prefix', 'tenant_' . $slug . '_cache_');
        Config::set('session.cookie', 'tenant_' . $slug . '_session');
        Config::set('database.redis.options.prefix', 'tenant_' . $slug . ':');
        Config::set('filesystems.disks.local.root', $this->getPrivateStoragePath($slug));
        Config::set('filesystems.disks.public.root', $this->getPublicStoragePath($slug));


        if ($tenantDomain) {
            $appUrl = "https://" . $tenantDomain;
        } else {
            $appUrl = "https://" . $slug . "." . config('multi-tenancy.tenant_main_domain');
        }

        Config::set('app.url', $appUrl);

        Config::set('filesystems.disks.public.url', $this->getPublicStorageUrl($slug));


        // Configure Redis database numbers to ensure isolation if using numeric databases
        if (config('session.driver') === 'redis') {
            // Use a deterministic hash to assign a Redis database number based on tenant slug
            $redisDbNumber = abs(crc32($slug) % 10) + 1; // Using databases 1-10 (0 is default)
            Config::set('database.redis.cache.database', $redisDbNumber);

            // If session uses Redis, configure it with the same database
            if (config('session.driver') === 'redis') {
                Config::set('session.connection', 'cache');
            }
        }

        Config::set('logging.channels.tenant', [
            'driver' => 'daily',
            'path' => storage_path('logs/tenants/' . $slug . '/' . $slug . '.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
        ]);
        Config::set('logging.default', 'tenant');
    }

    /**
     * Get the current tenant slug.
     *
     * @return string|null
     */
    public function getCurrentTenantSlug()
    {
        return $this->currentTenantSlug;
    }

    public function setCurrentTenantSlug(string $slug)
    {
        $this->currentTenantSlug = $slug;
    }

    public function getDatabasePath(string $slug)
    {
        $tenantBasePath = storage_path('app/tenants/' . $slug . '/database');
        $databasePath = $tenantBasePath . '/database.sqlite';
        return $databasePath;
    }

    public function getPrivateStoragePath(string $slug): string
    {
        return storage_path('app/private/tenants/' . $slug);
    }

    public function getPublicStoragePath(string $slug): string
    {
        return storage_path('app/public/tenants/' . $slug);
    }

    public function getPublicStorageUrl(string $slug): string
    {
        return config('app.url') . '/storage/tenants/' . $slug;
    }

    public function initializeTenantDatabaseForCurrentHost(): void
    {
        if ($this->isMainDomain()) {
            return;
        }
        $tenantInfo = $this->getTenantInfo();
        $this->initializeTenantDatabase(
            $tenantInfo->getSlug(),
            $tenantInfo->getUserName(),
            $tenantInfo->getUserEmail()
        );
    }


    public function initializeTenantDatabase(string $slug, string $userName, string $email): void
    {
        $this->switchTo($slug);

        $databasePath = $this->getDatabasePath($slug);
        if (file_exists($databasePath)) {
            return;
        }

        @mkdir(dirname($databasePath), 0777, true);
        @mkdir($this->getPrivateStoragePath($slug), 0777, true);
        @mkdir($this->getPublicStoragePath($slug), 0777, true);

        touch($databasePath);

        // Run migrations
        Artisan::call('migrate', [
            '--path' => [
                'database/migrations',
                'vendor/nnjeim/world/src/Database/Migrations',
                'vendor/spatie/laravel-permission/database/migrations',
            ],
        ]);


        // Run seeders
        Artisan::call('db:seed');

        $this->createTenantUser($slug, $userName, $email);
    }

    /**
     * Create tenant user and send welcome notification
     *
     * @param array $userInfo
     * @return void
     */
    public function createTenantUser(string $slug, string $userName, string $email): void
    {
        // Generate a random password
        $password = Str::random(12);
        $userModel = config('multi-tenancy.user_model');

        if ($userModel::where('email', $email)->exists()) {
            return;
        }

        // Create user
        $user = $userModel::create([
            'name' => $userName,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        // Assign admin role
        Artisan::call('shield:generate --all --panel admin');
        Artisan::call('shield:super-admin');

        // Send welcome notification
        $user->notify(new TenantWelcomeNotification($password));
    }

    /**
     * Get tenant information from the accounting system
     *
     * @param string|null $domain
     * @return TenantDTO
     * @throws TenantNotFoundException
     * @throws TenantFetchException
     */
    public function getTenantInfo(?string $domain = null): TenantDTO
    {
        if (!$domain) {
            $domain = request()->getHost();
        }
        $doCache = config('multi-tenancy.cache_tenant_config', true);
        if ($doCache) {
            $tenantInfo = Cache::get('tenant_info_' . $domain);
            if ($tenantInfo instanceof TenantDTO) {
                return $tenantInfo;
            }
        }

        $response = Http::timeout(config('multi-tenancy.accounting_timeout', 30))
            ->withOptions(['verify' => false])
            ->get(config('multi-tenancy.accounting_url') . '/api/apps/info', [
                'domain' => $domain
            ]);

        if (!$response->successful()) {
            if ($response->status() === 422 || ($response->json()['success'] ?? false) === false) {
                throw new TenantNotFoundException($domain);
            }
            Log::error('Failed to get tenant information: ' . $response->body());
            throw new TenantFetchException($domain, $response->body());
        }

        $userApp = $response->json()['data']['userApp'];
        $tenantDTO = new TenantDTO($userApp);

        if ($doCache) {
            Cache::put('tenant_info_' . $domain, $tenantDTO, config('multi-tenancy.cache_ttl', 60));
        }
        return $tenantDTO;
    }


    public function isMainDomain(): bool
    {
        if (App::runningInConsole()) {
            return true;
        }

        $appUrlHost = config('multi-tenancy.tenant_main_domain');
        return request()->getHost() === $appUrlHost;
    }


    public function isTenantDatabaseExists(): bool
    {
        $slug = $this->getCurrentTenantSlug();
        if (!$slug) {
            return false;
        }
        $databasePath = $this->getDatabasePath($slug);
        return file_exists($databasePath);
    }
}
