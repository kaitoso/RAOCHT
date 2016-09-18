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
        $this->logger->critical($exception->getFile()."[{$exception->getLine()}]: {$exception->getMessage()}\nFull Exception: {$exception->getTraceAsString()}");
        if($request->isXHR()){
            $response->getBody()
                ->write(json_encode(['error' => 'Hubo un error interno al procesar la solicitud.']));
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'application/json; charset=utf-8');
        }
        return $response->withStatus(302)->withHeader('Location', $request->getUri()->getBasePath().'/error.html');
    }


}