<?php
return [
    'settings' => [
        'displayErrorDetails' => true,
        'debug' => true,
        'view' => [
            'template_path' => __DIR__ . '/rao/Views',
            'twig' => [
                'cache' => __DIR__ . '/../cache/twig',
                'debug' => true,
                'auto_reload' => true,
            ],
        ],
        // Monolog settings
        'logger' => [
            'name' => 'ith-app',
            'path' => __DIR__ . '/../logs/app.log',
        ],
        'database' => [
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'rao_chat',
            'username'  => 'rao',
            'password'  => 'Xu2iXI7i14RA',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ],
        'facebook' => [
            'app_id' => '1659916867664250',
            'app_secret' => '48d76e9e12e98fe17ccbc4b435deca0c',
            'default_graph_version' => 'v2.7',
        ]
    ],
];
