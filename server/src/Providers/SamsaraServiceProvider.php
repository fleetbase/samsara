<?php

namespace Fleetbase\Samsara\Providers;

use Fleetbase\Providers\CoreServiceProvider;
use Fleetbase\Samsara\Models\SamsaraCredential;
use Fleetbase\Samsara\Models\SamsaraVehicle;
use Fleetbase\Samsara\Models\SamsaraWebhookEvent;
use Fleetbase\Samsara\Policies\SamsaraCredentialPolicy;
use Fleetbase\Samsara\Policies\SamsaraVehiclePolicy;
use Fleetbase\Samsara\Policies\SamsaraWebhookEventPolicy;
use Fleetbase\Samsara\Services\SamsaraApiService;
use Fleetbase\Samsara\Services\SamsaraWebhookService;
use Fleetbase\Samsara\Services\SamsaraSyncService;
use Fleetbase\Samsara\Console\Commands\SamsaraSyncCommand;
use Fleetbase\Samsara\Auth\Schemas\Samsara as SamsaraAuthSchema;
use Illuminate\Support\Facades\Gate;

if (!class_exists(CoreServiceProvider::class)) {
    throw new \Exception('Extension cannot be loaded without `fleetbase/core-api` installed!');
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
        SamsaraCredential::class => SamsaraCredentialPolicy::class,
        SamsaraVehicle::class => SamsaraVehiclePolicy::class,
        SamsaraWebhookEvent::class => SamsaraWebhookEventPolicy::class,
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

        // Register Samsara services as singletons
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

        // Register middleware
        $this->app['router']->aliasMiddleware(
            'samsara.company.scope',
            \Fleetbase\Samsara\Http\Middleware\SamsaraCompanyScope::class
        );

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SamsaraSyncCommand::class,
            ]);
        }
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
        $this->registerObservers();
        $this->registerExpansionsFrom(__DIR__ . '/../Expansions');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');

        // Register policies
        // $this->registerPolicies();

        // // Register auth schema
        // $this->registerAuthSchema();

        // Register additional gates
        Gate::define('samsara.access', function ($user) {
            return $user->hasPermissionTo('samsara view');
        });

        Gate::define('samsara.admin', function ($user) {
            return $user->hasPermissionTo('samsara admin');
        });
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
    }

    /**
     * Register the Samsara auth schema.
     *
     * @return void
     */
    protected function registerAuthSchema()
    {
        // Register the auth schema when the application is booted
        $this->app->booted(function () {
            if (class_exists(\Fleetbase\Models\Permission::class)) {
                \Fleetbase\Models\Permission::registerSchema(SamsaraAuthSchema::class);
            }
        });
    }
}

