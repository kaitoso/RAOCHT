<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Container;
use Slim\Http\Request;

/**
 *
 */
abstract class BaseController
{
    public function __construct(Container $c)
    {
        $this->container = $c;
    }

    public function __get($property)
    {
        if($this->container->{$property}){
            return $this->container->{$property};
        }
    }

    protected function withRedirect(Response $response, $location = '/')
    {
        $this->session->set('redirect', $_SERVER['REQUEST_URI']);
        return $response->withStatus(302)->withHeader('Location', $location);
    }

    protected function withRedirectWithout(Response $response, $location = '/')
    {
        $this->session->set('redirect', null);
        return $response->withStatus(302)->withHeader('Location', $location);
    }

    protected function showError(Response $response, $mensaje = 'Acceso Prohibido', $httpcode = 403)
    {
        $this->session->set('errorResponse', $mensaje);
        if (!empty($_SERVER['HTTP_REFERER'])) {
            return $response->withStatus($httpcode)->withHeader('Location', $_SERVER['HTTP_REFERER']);
        } else {
            return $response->withStatus($httpcode)->withHeader('Location', '/');
        }
    }

    protected function showJSONResponse(Response $response, Array $data, $httpcode = 200)
    {
        $response->getBody()
            ->write(json_encode($data));
        return $response->withStatus($httpcode)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
