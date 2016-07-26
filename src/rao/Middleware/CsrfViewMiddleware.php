<?php

namespace App\Middleware;

class CsrfViewMiddleware extends Middleware
{
    public function __invoke($request, $response, $next)
    {
        $this->container->view->getEnvironment()->addGlobal('csrf', [
            'field' => '<input class="hidden" name="raoToken" value="' . $this->container->session->get('token') .'">'
        ]);
        $response = $next($request, $response);
        return $response;
    }
}