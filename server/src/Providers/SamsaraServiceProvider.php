<?php

namespace Fleetbase\Samsara\Providers;

use Fleetbase\FleetOps\Providers\FleetOpsServiceProvider;
use Fleetbase\Providers\CoreServiceProvider;
use Fleetbase\Samsara\Models\SamsaraCredential;
use Fleetbase\Samsara\Models\SamsaraVehicle;
use Fleetbase\Samsara\Models\SamsaraWebhookEvent;
use Fleetbase\Samsara\Policies\SamsaraCredentialPolicy;
use Fleetbase\Samsara\Policies\SamsaraVehiclePolicy;
use Fleetbase\Samsara\Policies\SamsaraWebhookEventPolicy;
use Fleetbase\Samsara\Services\SamsaraApiService;
use Fleetbase\Samsara\Services\SamsaraSyncService;
use Fleetbase\Samsara\Services\SamsaraWebhookService;
use Illuminate\Support\Facades\Gate;

if (!class_exists(CoreServiceProvider::class)) {
    throw new \Exception('Extension cannot be loaded without `fleetbase/core-api` installed!');
}

if (!class_exists(FleetOpsServiceProvider::class)) {
    throw new \Exception('Storefront cannot be loaded without `fleetbase/fleetops-api` installed!');
}

/**
 * Samsara extension service provider.
 */
class SamsaraServiceProvider extends CoreServiceProvider
{
    /**
     * The observers registered with the service provider.
     *
     * @var array
     */
    public $observers = [];

    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        SamsaraCredential::class   => SamsaraCredentialPolicy::class,
        SamsaraVehicle::class      => SamsaraVehiclePolicy::class,
        SamsaraWebhookEvent::class => SamsaraWebhookEventPolicy::class,
    ];

    /**
     * The console commands registered with the service provider.
     *
     * @var array
     */
    public $commands = [
        \Fleetbase\Samsara\Console\Commands\SamsaraSyncCommand::class,
    ];

    /**
     * Register any application services.
     *
     * Within the register method, you should only bind things into the
     * service container. You should never attempt to register any event
     * listeners, routes, or any other piece of functionality within the
     * register method.
     *
     * More information on this can be found in the Laravel documentation:
     * https://laravel.com/docs/8.x/providers
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(CoreServiceProvider::class);
        $this->app->register(FleetOpsServiceProvider::class);
        $this->registerServices();
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     *
     * @throws \Exception if the `fleetbase/core-api` package is not installed
     */
    public function boot()
    {
        $this->registerCommands();
        $this->registerObservers();
        $this->registerPolicies();
        $this->registerExpansionsFrom(__DIR__ . '/../Expansions');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
        $this->mergeConfigFrom(__DIR__ . '/../../config/samsara.php', 'samsara');
    }

    /**
     * Register the application's policies.
     *
     * @return void
     */
    public function registerPolicies()
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Register additional gates
        Gate::define('samsara.access', function ($user) {
            return $user->hasPermissionTo('samsara view');
        });

        Gate::define('samsara.admin', function ($user) {
            return $user->hasPermissionTo('samsara admin');
        });
    }

    /**
     * Register samsara services to application container as singletons.
     *
     * @return void
     */
    public function registerServices()
    {
        $this->app->singleton(SamsaraApiService::class, function ($app) {
            return new SamsaraApiService();
        });

        $this->app->singleton(SamsaraWebhookService::class, function ($app) {
            return new SamsaraWebhookService();
        });

        $this->app->singleton(SamsaraSyncService::class, function ($app) {
            return new SamsaraSyncService(
                $app->make(SamsaraApiService::class),
                $app->make(SamsaraWebhookService::class)
            );
        });
    }
}
