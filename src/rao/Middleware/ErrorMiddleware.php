<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;
use App\Handler\Error\ErrorHandler;

class ErrorMiddleware{
    function __invoke(Request $request, Response $response, $next)
    {
        $app = $next;
        $container = $app->getContainer();
        $settings = $container['settings'];
        if(!empty($settings['debug']) && $settings['debug']){
            $handler = null;
            $handler = !\Whoops\Util\Misc::isAjaxRequest() ? new PrettyPageHandler() : new JsonResponseHandler();
            $whoops = new \Whoops\Run;
            $whoops->pushHandler($handler);
            $whoops->register();

            $container['errorHandler'] = function() use ($whoops) {
                return new ErrorHandler($whoops);
            };
            $container['whoops'] = $whoops;
        }
        return $app($request, $response);
    }

}