<?php
/**
 * Created by PhpStorm.
 * User: joseg
 * Date: 06/08/2016
 * Time: 06:40 PM
 */

namespace App\Handler\Error;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Handlers\NotFound as Not;

class NotFound extends Not
{

    private $view;

    public function __construct($view) {
        $this->view = $view;
    }

    function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->view->render($response, '404.twig');
        return $response->withStatus(404);
    }
}