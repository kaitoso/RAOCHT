<?php
namespace App\Middleware;
use App\Model\User;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AdminMiddleware extends Middleware
{
    protected $user;

    public function __invoke(Request $request, Response $response, $next)
    {
        if(empty($this->container->session->get('user_id')))
        {
            $this->container->flash->addMessage('error', 'Necesitas iniciar sesión antes de acceder a esta sección');
            return $this->withRedirect($response, $this->container->router->pathFor('auth.login'));
        }
        $this->user = User::find($this->container->session->get('user_id'));
        $permissions = json_decode($this->user->getRank->permissions);
        if(count($permissions) === 0){
            $this->container->flash->addMessage('error', 'No tienes los suficientes permisos para acceder a esta sección');
            return $this->withRedirect($response, $this->container->router->pathFor('main.error'));
        }
        $permisos = require __DIR__.'/../Config/RankPermissions.php';
        $path = explode('/', $request->getUri()->getPath());
        if(!empty($path[2]) && !in_array($path[2], ['search', 'stats'])){
            $currentPermisos = array_intersect_key($permisos, array_flip($permissions));
            if(empty($currentPermisos[$path[2]])){ // No tiene el permiso de acceder aquí
                $this->container->flash->addMessage('error', 'No tienes los suficientes permisos para acceder a esta sección');
                return $this->withRedirect($response, $this->container->router->pathFor('admin.main'));
            }
        }
        $permisos = array_intersect_key($permisos, array_flip($permissions));
        uasort($permisos, function($a, $b){
            if($a['description'] > $b['description'])
                return 1;
            return -1;
        });
        $this->container->view->getEnvironment()->addGlobal('user', $this->user);
        $this->container->view->getEnvironment()->addGlobal(
            'permisos',
            array_intersect_key($permisos, array_flip($permissions))
        );
        return $next($request, $response);
    }


}