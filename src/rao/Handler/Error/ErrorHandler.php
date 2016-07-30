<?php
namespace App\Handler\Error;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Handlers\Error;

final class ErrorHandler Extends Error{
    protected $logger;

    /**
     * ErrorHandler constructor.
     * @param $logger
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response, \Exception $exception)
    {
        $this->logger->critical($exception->getFile()."[{$exception->getLine()}]: {$exception->getMessage()}.\nFull-Stack: ".$exception->getTraceAsString());
        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('¡NOOOOOOOOOOO! Algo falló en el chat. Nuestro robot ya lo estará arreglando enseguida.');
    }


}