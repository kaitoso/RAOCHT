<?php

namespace App\Controller;

use App\Model\AuthToken;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class SubscriptionController extends BaseController
{
    public function getActivation(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            return $this->withRedirect($response, $this->router->pathFor('main.page'));
        }
        $inputToken = $args['token'];
        $token = AuthToken::where('token', $inputToken)->first();
        if(!$token){
            $this->logger->notice("[Activation] Token no registrado: {$inputToken}");
            $this->flash->addMessage('error', 'Hubo un problema al activar tu cuenta. Envíanos un mensaje por facebook con el nombre de tu cuenta.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $user = $token->usuario;
        $user->activated = 1;
        $user->save();
        $token->delete();
        $this->flash->addMessage('success', '¡Has activado correctamente tu cuenta del chat! Por favor, inicia sesión con tus credenciales.');
        return $this->withRedirect($response,  $this->router->pathFor('auth.login'));
    }

    public function getUnsubscribe(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            return $this->withRedirect($response, $this->router->pathFor('main.page'));
        }
        $inputToken = $args['token'];
        $token = AuthToken::where('token', $inputToken)->first();
        if(!$token){
            $this->logger->notice("[Unsubscribe] Token no registrado: {$inputToken}");
            $this->flash->addMessage('error', 'Hubo un problema al eliminar tu cuenta. Envíanos un mensaje a Facebook con tu correo electrónico.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $user = $token->usuario;
        $avatarPath = __DIR__.'/../../../public/avatar';
        if(file_exists($avatarPath.'/b/'.$user->image)){
            unlink($avatarPath.'/b/'.$user->image);
        }
        if(file_exists($avatarPath.'/s/'.$user->image)){
            unlink($avatarPath.'/s/'.$user->image);
        }
        $token->delete();
        $user->delete();
        $this->flash->addMessage('success', '¡Se ha eliminado correctamente la cuenta!');
        return $this->withRedirect($response,  $this->router->pathFor('auth.login'));
    }
}