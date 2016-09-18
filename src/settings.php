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
            'name' => 'chat-app',
            'path' => __DIR__ . '/../logs/app.log',
        ],
        'database' => [
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'animeobs_chat',
            'username'  => 'animeobs_chat',
            'password'  => 'Xu2iXI7i14RA',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => ''
        ],
        'smtp' => [
            'smtp_user' => 'chat@animeobsesion.net',
            'smtp_password' => '$l(^Sd+xKQVT',
            'smtp_server' => '127.0.0.1',
            'smtp_port' => 25
        ],
        'facebook' => [
            'app_id' => '1659916867664250',
            'app_secret' => '48d76e9e12e98fe17ccbc4b435deca0c',
            'default_graph_version' => 'v2.7',
        ],
        'twitter' => [
            'consumer_key' => 'YaL0UUnURWDMVZUwAgbA',
            'consumer_secret' => '3QndwOfyOULcoq7NN3D2YaFoywROPWgyjFmBb4RM'
        ],
        'google' => [
            'client_id' => '432177929737-uknlkb0kvnuqd93rag8ndcn2a2ki2r51.apps.googleusercontent.com',
            'client_secret' => 'eCnVeC0orCheTlssl6OK5Ic6'
        ]
    ],
];
