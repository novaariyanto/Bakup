<?php

return [

    'navigation' => [
        [
            'label' => 'Overview',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard'],
            ],
        ],
        [
            'label' => 'Backup',
            'items' => [
                ['label' => 'Database Connections', 'route' => 'database-connections.index', 'icon' => 'database'],
                ['label' => 'Backup Profiles', 'route' => 'backup-profiles.index', 'icon' => 'profile'],
                ['label' => 'MyDumper Export', 'route' => 'mydumper-exports.index', 'icon' => 'export'],
                ['label' => 'Storage Destinations', 'route' => 'storage-destinations.index', 'icon' => 'storage'],
                ['label' => 'Backup History', 'route' => 'backup-history.index', 'icon' => 'history'],
            ],
        ],
        [
            'label' => 'System',
            'items' => [
                ['label' => 'Notifications', 'route' => 'notifications.index', 'icon' => 'bell'],
                ['label' => 'Activity Log', 'route' => null, 'icon' => 'activity', 'disabled' => true],
            ],
        ],
    ],

];
