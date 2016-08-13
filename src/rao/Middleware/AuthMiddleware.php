<?php
namespace App\Middleware;
use App\Model\User;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AuthMiddleware extends Middleware
{
    protected $user;

    public function __invoke(Request $request, Response $response, $next)
    {
        if(empty($this->container->session->get('user_id')))
        {
            if($request->isXhr()){
                $response->getBody()
                    ->write(json_encode(['error' => 'Necesitas iniciar sesión para acceder a esta sección.']));
                return $response->withStatus(403)
                    ->withHeader('Content-Type', 'application/json; charset=utf-8');
            }
            $this->container->flash->addMessage('error', 'Necesitas iniciar sesión antes de acceder a esta sección');
            return $this->withRedirect($response, $this->container->router->pathFor('auth.login'));
        }
        return $next($request, $response);
    }


}