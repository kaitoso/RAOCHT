<?php
// Application middleware

// e.g: $app->add(new \Slim\Csrf\Guard);
//$app->add(new \App\Middleware\ErrorMiddleware());
$app->add(new RKA\Middleware\IpAddress(true));

$app->add(new \App\Middleware\SessionMiddleware($container));
$app->add(new \App\Middleware\ValidationErrorsMiddleware($container));
$app->add(new \App\Middleware\OldInputMiddleware($container));