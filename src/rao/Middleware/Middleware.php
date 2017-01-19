<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;

class Middleware
{
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    protected function withRedirect(Response $response, $location = '/')
    {
        $this->container->session->set('redirect', $_SERVER['REQUEST_URI']);
        return $response->withStatus(302)->withHeader('Location', $location);
    }

    protected function withRedirectWithout(Response $response, $location = '/')
    {
        $this->container->session->set('redirect', null);
        return $response->withStatus(302)->withHeader('Location', $location);
    }
}