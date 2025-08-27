<?php

namespace Fleetbase\Samsara\Auth\Schemas;

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
            'name'    => 'credential',
            'actions' => ['create', 'view', 'update', 'delete', 'export', 'import', 'test-connection'],
        ],
        [
            'name'    => 'vehicle',
            'actions' => ['view', 'sync', 'link', 'unlink', 'export', 'import'],
        ],
        [
            'name'    => 'webhook-event',
            'actions' => ['view', 'retry', 'export'],
        ],
        [
            'name'    => 'sync',
            'actions' => ['trigger', 'view-status', 'health-check'],
        ],
    ];

    /**
     * Policies provided by this schema.
     */
    public array $policies = [
        [
            'name'        => 'SamsaraViewer',
            'description' => 'Policy for viewing Samsara data and basic operations.',
            'permissions' => [
                'see extension',
                'view credential',
                'view vehicle',
                'view webhook-event',
                'view-status sync',
            ],
        ],
        [
            'name'        => 'SamsaraOperator',
            'description' => 'Policy for operational Samsara tasks including sync and vehicle management.',
            'permissions' => [
                'see extension',
                'view credential',
                'test-connection credential',
                'view vehicle',
                'sync vehicle',
                'link vehicle',
                'unlink vehicle',
                'view webhook-event',
                'retry webhook-event',
                'trigger sync',
                'view-status sync',
                'health-check sync',
            ],
        ],
        [
            'name'        => 'SamsaraManager',
            'description' => 'Policy for managing Samsara credentials and advanced operations.',
            'permissions' => [
                'see extension',
                'create credential',
                'view credential',
                'update credential',
                'test-connection credential',
                'export credential',
                'import credential',
                'view vehicle',
                'sync vehicle',
                'link vehicle',
                'unlink vehicle',
                'export vehicle',
                'import vehicle',
                'view webhook-event',
                'retry webhook-event',
                'export webhook-event',
                'trigger sync',
                'view-status sync',
                'health-check sync',
            ],
        ],
        [
            'name'        => 'SamsaraAdministrator',
            'description' => 'Policy for full administrative access to all Samsara features.',
            'permissions' => [
                'see extension',
                'create credential',
                'view credential',
                'update credential',
                'delete credential',
                'export credential',
                'import credential',
                'test-connection credential',
                'view vehicle',
                'sync vehicle',
                'link vehicle',
                'unlink vehicle',
                'export vehicle',
                'import vehicle',
                'view webhook-event',
                'retry webhook-event',
                'export webhook-event',
                'trigger sync',
                'view-status sync',
                'health-check sync',
            ],
        ],
    ];
}
