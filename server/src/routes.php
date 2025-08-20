<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix(config('samsara.api.routing.prefix', 'starter'))->namespace('Fleetbase\Samsara\Http\Controllers')->group(
    function ($router) {
        /*
        |--------------------------------------------------------------------------
        | Samsara API Routes
        |--------------------------------------------------------------------------
        |
        | Primary internal routes for console.
        */
        $router->prefix(config('samsara.api.routing.internal_prefix', 'int'))->group(
            function ($router) {
                $router->group(
                    ['prefix' => 'v1', 'middleware' => ['fleetbase.protected']],
                    function ($router) {
                        // Samsara Credentials Management
                        $router->prefix('credentials')->group(function ($router) {
                            $router->get('/', 'SamsaraCredentialController@index');
                            $router->post('/', 'SamsaraCredentialController@store');
                            $router->get('/active', 'SamsaraCredentialController@getActive');
                            $router->post('/test', 'SamsaraCredentialController@testCredentials');
                            $router->get('/{id}', 'SamsaraCredentialController@show');
                            $router->patch('/{id}', 'SamsaraCredentialController@update');
                            $router->delete('/{id}', 'SamsaraCredentialController@destroy');
                            $router->post('/{id}/test', 'SamsaraCredentialController@testConnection');
                            $router->post('/{id}/activate', 'SamsaraCredentialController@activate');
                            $router->get('/{id}/stats', 'SamsaraCredentialController@getSyncStats');
                        });

                        // Samsara Vehicle Management
                        $router->prefix('vehicles')->group(function ($router) {
                            $router->get('/', 'SamsaraVehicleController@index');
                            $router->post('/', 'SamsaraVehicleController@store');
                            $router->get('/available', 'SamsaraVehicleController@getAvailable');
                            $router->post('/sync-all', 'SamsaraVehicleController@syncAll');
                            $router->get('/{id}', 'SamsaraVehicleController@show');
                            $router->patch('/{id}', 'SamsaraVehicleController@update');
                            $router->delete('/{id}', 'SamsaraVehicleController@destroy');
                            $router->post('/{id}/sync', 'SamsaraVehicleController@sync');
                            $router->get('/{id}/location-history', 'SamsaraVehicleController@getLocationHistory');
                            $router->post('/{id}/link', 'SamsaraVehicleController@linkVehicle');
                            $router->post('/{id}/unlink', 'SamsaraVehicleController@unlinkVehicle');
                        });

                        // Webhook Events Management
                        $router->prefix('webhook-events')->group(function ($router) {
                            $router->get('/', 'SamsaraWebhookController@getEvents');
                            $router->get('/stats', 'SamsaraWebhookController@getStats');
                            $router->get('/webhook-url', 'SamsaraWebhookController@getWebhookUrl');
                            $router->post('/test', 'SamsaraWebhookController@test');
                            $router->get('/{id}', 'SamsaraWebhookController@getEvent');
                            $router->post('/{id}/retry', 'SamsaraWebhookController@retryEvent');
                        });

                        // Sync and Health Check Endpoints
                        $router->prefix('sync')->group(function ($router) {
                            $router->post('/full', function () {
                                $syncService = app(\Fleetbase\Samsara\Services\SamsaraSyncService::class);
                                return response()->json($syncService->runFullSync());
                            });
                            
                            $router->post('/stale-vehicles', function () {
                                $syncService = app(\Fleetbase\Samsara\Services\SamsaraSyncService::class);
                                return response()->json($syncService->syncStaleVehicles());
                            });
                            
                            $router->post('/pending-webhooks', function () {
                                $syncService = app(\Fleetbase\Samsara\Services\SamsaraSyncService::class);
                                return response()->json($syncService->processPendingWebhooks());
                            });
                            
                            $router->get('/status', function () {
                                $syncService = app(\Fleetbase\Samsara\Services\SamsaraSyncService::class);
                                return response()->json($syncService->getSyncStatus());
                            });
                            
                            $router->get('/health', function () {
                                $syncService = app(\Fleetbase\Samsara\Services\SamsaraSyncService::class);
                                return response()->json($syncService->healthCheck());
                            });
                            
                            $router->post('/cleanup', function () {
                                $syncService = app(\Fleetbase\Samsara\Services\SamsaraSyncService::class);
                                $days = request()->input('days', 30);
                                return response()->json($syncService->cleanup($days));
                            });
                        });
                    }
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Public Webhook Routes
        |--------------------------------------------------------------------------
        |
        | Public routes for receiving webhooks from Samsara (no authentication).
        */
        $router->post('webhook/{companyId}', 'SamsaraWebhookController@handle')
            ->name('samsara.webhook');
    }
);
