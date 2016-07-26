<?php

namespace App\Middleware;


class OldInputMiddleware extends Middleware
{
    public function __invoke($request, $response, $next)
    {
        $this->container->view->getEnvironment()->addGlobal('old', $this->container->session->get('oldInput'));
        $this->container->session->set('oldInput', $request->getParams());

        $response = $next($request, $response);
        return $response;
    }
}