<?php

namespace Fleetbase\Samsara\Auth\Schemas;

use Fleetbase\Samsara\Auth\Directives\SamsaraCredentials;
use Fleetbase\Samsara\Auth\Directives\SamsaraVehicles;
use Fleetbase\Samsara\Auth\Directives\SamsaraWebhooks;
use Fleetbase\Samsara\Auth\Directives\SamsaraSync;

class Samsara
{
    /**
     * The permission schema Name.
     */
    public string $name = 'samsara';

    /**
     * The permission schema Polict Name.
     */
    public string $policyName = 'Samsara';

    /**
     * Guards these permissions should apply to.
     */
    public array $guards = ['sanctum'];

    /**
     * The permission schema resources.
     */
    public array $resources = [
        [
            'name' => 'credential',
            'actions' => ['create', 'view', 'update', 'delete', 'export', 'import', 'test-connection'],
        ],
        [
            'name' => 'vehicle',
            'actions' => ['view', 'sync', 'link', 'unlink', 'export', 'import'],
        ],
        [
            'name' => 'webhook-event',
            'actions' => ['view', 'retry', 'export'],
        ],
        [
            'name' => 'sync',
            'actions' => ['trigger', 'view-status', 'health-check'],
        ],
    ];

    /**
     * Policies provided by this schema.
     */
    public array $policies = [
        [
            'name' => 'SamsaraViewer',
            'description' => 'Policy for viewing Samsara data and basic operations.',
            'permissions' => [
                'see extension',
                'credential view',
                'vehicle view',
                'webhook-event view',
                'sync view-status',
            ],
        ],
        [
            'name' => 'SamsaraOperator',
            'description' => 'Policy for operational Samsara tasks including sync and vehicle management.',
            'permissions' => [
                'see extension',
                'credential view',
                'credential test-connection',
                'vehicle view',
                'vehicle sync',
                'vehicle link',
                'vehicle unlink',
                'webhook-event view',
                'webhook-event retry',
                'sync trigger',
                'sync view-status',
                'sync health-check',
            ],
        ],
        [
            'name' => 'SamsaraManager',
            'description' => 'Policy for managing Samsara credentials and advanced operations.',
            'permissions' => [
                'see extension',
                'credential create',
                'credential view',
                'credential update',
                'credential test-connection',
                'credential export',
                'credential import',
                'vehicle view',
                'vehicle sync',
                'vehicle link',
                'vehicle unlink',
                'vehicle export',
                'vehicle import',
                'webhook-event view',
                'webhook-event retry',
                'webhook-event export',
                'sync trigger',
                'sync view-status',
                'sync health-check',
            ],
        ],
        [
            'name' => 'SamsaraAdministrator',
            'description' => 'Policy for full administrative access to all Samsara features.',
            'permissions' => [
                'see extension',
                'credential create',
                'credential view',
                'credential update',
                'credential delete',
                'credential export',
                'credential import',
                'credential test-connection',
                'vehicle view',
                'vehicle sync',
                'vehicle link',
                'vehicle unlink',
                'vehicle export',
                'vehicle import',
                'webhook-event view',
                'webhook-event retry',
                'webhook-event export',
                'sync trigger',
                'sync view-status',
                'sync health-check',
            ],
        ],
    ];
}

