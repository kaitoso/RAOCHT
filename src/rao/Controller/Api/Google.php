<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Model\Ban;
use App\Model\User;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Google_Client;
class Google extends BaseController
{
    public function getIndex(Request $request, Response $response, $args)
    {
        if(!empty($this->session->get('user_ban'))){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        if ($this->session->get('user_id') !== null) {
            return $this->withRedirect($response, $this->router->pathFor('main.page'));
        }
        $ban = Ban::where('ip', $request->getAttribute('ip_address'))->first();
        if($ban){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        $client = $this->google;
        $client->setRedirectUri($request->getUri()->getBaseUrl().$this->router->pathFor('auth.google.callback'));
        return $this->withRedirectWithout($response, $client->createAuthUrl());
    }

    public function getLink(Request $request, Response $response, $args)
    {
        $client = $this->google;
        $client->setRedirectUri($request->getUri()->getBaseUrl().$this->router->pathFor('cuenta.google.callback'));
        return $this->withRedirectWithout($response, $client->createAuthUrl());
    }

    public function getCallback(Request $request, Response $response, $args)
    {
        if(!empty($this->session->get('user_ban'))){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        $query = $request->getQueryParams();
        if(!empty($query['error'])){
            $this->flash->addMessage(
                'error',
                'Denegaste los permisos para acceder a tu cuenta. Ingresa con tus credenciales.'
            );
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $client = $this->google;
        $client->setRedirectUri($request->getUri()->getBaseUrl().$this->router->pathFor('auth.google.callback'));
        $client->authenticate($query['code']);
        $token_data = $client->verifyIdToken();
        $user_id = $token_data['sub'];
        $user_email = $token_data['email'];
        $user = User::where('googleId', $user_id)->first();
        if(!$user){
            $this->session->set('social_email', $user_email);
            $this->session->set('google_id', $user_id);
            $this->flash->addMessage(
                'error',
                'Aún no has ligado esta cuenta de Google+ a una cuenta del chat. Ingresa con tus credenciales. '.$user_id
            );
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $this->session->set('user_id', $user->id);
        $this->session->set('user', $user->user);
        $this->session->set('rank', $user->rank);
        /* Actualizar el ultimo inicio de sesión */
        $user->ip = $request->getAttribute('ip_address');
        $user->lastLogin = date('Y-m-d H:i:s');
        $user->save();
        return $this->withRedirectWithout($response, $this->router->pathFor('main.page'));
    }

    public function getLinkCallback(Request $request, Response $response, $args)
    {
        $query = $request->getQueryParams();
        if(!empty($query['error'])){
            $this->flash->addMessage(
                'error',
                'Denegaste los permisos para acceder a tu cuenta. Ingresa con tus credenciales.'
            );
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $client = $this->google;
        $client->setRedirectUri($request->getUri()->getBaseUrl().$this->router->pathFor('cuenta.google.callback'));
        $client->authenticate($query['code']);
        $token_data = $client->verifyIdToken();
        $user_id = $token_data['sub'];
        $user = User::find($this->session->get('user_id'));
        if(!$user){
            $this->flash->addMessage('error', '¡No has iniciado sesión!');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        /* Actualizar el ultimo inicio de sesión */
        $user->googleId = $user_id;
        $user->ip = $request->getAttribute('ip_address');
        $user->save();
        $this->flash->addMessage('social', '¡Se ligo esta cuenta a Google+!');
        return $this->withRedirectWithout($response, $this->router->pathFor('cuenta.main').'#formSocial');
    }

    public function getUnlink(Request $request, Response $response, $args)
    {
        $user = User::find($this->session->get('user_id'));
        if(!$user){
            $this->flash->addMessage('error', '¡No has iniciado sesión!');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        if(empty($user->googleId)){
            $this->flash->addMessage('social-error', '¡Esta cuenta no está ligada a ninguna cuenta de Google!');
            return $this->withRedirectWithout($response, $this->router->pathFor('cuenta.main').'#formSocial');
        }
        // Guardamos token
        $user->googleId = null;
        $user->save();
        $this->flash->addMessage('social', 'Se desligo esta cuenta de Google+.');
        return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
    }
}