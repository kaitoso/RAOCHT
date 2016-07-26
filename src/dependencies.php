<?php
// DIC configuration
$container = $app->getContainer();

$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection($settings['settings']['database']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Eloquent
$container['db'] = function($c) use ($capsule){
    return $capsule;
};

// Redis
$container['redis'] = function($c){
    $redis = new Redis();
    $redis->pconnect('127.0.0.1', 6379);
    return $redis;
};

// Session
$container['session'] = function($c){
    return App\Handler\SessionHandler::getInstance();
};

// Flash
$container['flash'] = function ($c){
    return new \Slim\Flash\Messages();
};

// Twig
$container['view'] = function ($c) {
    $settings = $c->get('settings');
    $view = new \Slim\Views\Twig($settings['view']['template_path'], $settings['view']['twig']);
    // Add extensions
    $view->addExtension(new Slim\Views\TwigExtension($c->get('router'), $c->get('request')->getUri()));
    $view->addExtension(new Twig_Extension_Debug());
    $view->getEnvironment()->addGlobal('csrf', [
        'field' => '<input class="hidden" name="raoToken" value="' . $c->session->get('token') .'">',
        'token' => $c->session->get('token')
    ]);
    $view->getEnvironment()->addGlobal('flash', $c->flash);
    return $view;
};


// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], Monolog\Logger::DEBUG));
    return $logger;
};

$container['cookie'] = function($c){
    $request = $c->get('request');
    return new \Slim\Http\Cookies($request->getCookieParams());
};

$container['validator'] = function($c){
    return new App\Validation\Validator($c);
};

$container['token'] = function ($c){
    return new App\Security\Token();
};

$container['fb'] = function ($c){
    $fbSettings = $c->get('settings')['facebook'];
    return new Facebook\Facebook($fbSettings);
};

$container['App\MainController'] = function($c){
    return new App\Controller\MainController($c);
};

$container['App\CuentaController'] = function($c){
    return new App\Controller\CuentaController($c);
};

$container['App\SearchController'] = function($c){
    return new App\Controller\SearchController($c);
};

$container['App\AdminController'] = function($c){
    return new App\Controller\AdminController($c);
};

$container['App\Admin\SearchController'] = function ($c){
    return new App\Controller\Admin\SearchController($c);
};

$container['App\Admin\BanController'] = function ($c){
    return new App\Controller\Admin\BanController($c);
};

$container['App\Admin\RankController'] = function ($c){
    return new App\Controller\Admin\RankController($c);
};

$container['App\Admin\UserController'] = function ($c){
    return new App\Controller\Admin\UserController($c);
};

$container['App\Api\Facebook'] = function($c){
    return new App\Controller\Api\Facebook($c);
};