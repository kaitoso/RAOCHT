<?php
namespace App\Handler\Error;

use Exception;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Whoops\Run as WhoopsRun;

class WhoopsErrorHandler{
    private $whoops;

    public function __construct(WhoopsRun $run)
    {
        $this->whoops = $run;
    }

    function __invoke(Request $request, Response $response, Exception $exception)
    {
        $handler = WhoopsRun::EXCEPTION_HANDLER;
        ob_start();
        $this->whoops->$handler($exception);
        $content = ob_get_clean();
        $code = $exception instanceof \HttpException ? $exception->getCode() : 500;
        return $response
            ->withStatus($code)
            ->withHeader('Content-type', 'text/html')
            ->write($content);
    }


}