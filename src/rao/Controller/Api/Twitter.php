<?php
namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Model\User;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Abraham\TwitterOAuth\TwitterOAuth;

class Twitter extends BaseController
{
    public function getIndex(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            return $this->withRedirect($response, $this->router->pathFor('main.page'));
        }
        $request_token = $this->twitter->oauth('oauth/request_token', array(
            'oauth_callback' => $request->getUri()->getBaseUrl().$this->router->pathFor('auth.twitter.callback')
        ));
        $this->session->set('twitter_oauth', $request_token['oauth_token']);
        $this->session->set('twitter_oauth_secret', $request_token['oauth_token_secret']);
        $url = $this->twitter->url('oauth/authorize', array(
            'oauth_token' => $request_token['oauth_token']
        ));
        return $this->withRedirect($response, $url);
    }

    public function getLink(Request $request, Response $response, $args)
    {
        $request_token = $this->twitter->oauth('oauth/request_token', array(
            'oauth_callback' => $request->getUri()->getBaseUrl().$this->router->pathFor('cuenta.twitter.callback')
        ));
        $this->session->set('twitter_oauth', $request_token['oauth_token']);
        $this->session->set('twitter_oauth_secret', $request_token['oauth_token_secret']);
        $url = $this->twitter->url('oauth/authorize', array(
            'oauth_token' => $request_token['oauth_token']
        ));
        return $this->withRedirect($response, $url);
    }

    public function getCallback(Request $request, Response $response, $args)
    {
        $query = $request->getQueryParams();
        if($query['oauth_token'] !== $this->session->get('twitter_oauth')){
            return $this->showJSONResponse($response, ['error' => 'Éste token es inválido.']);
        }
        $sett = $this->settings['twitter'];
        try {
            $twitter = new TwitterOAuth(
                $sett['consumer_key'],
                $sett['consumer_secret'],
                $this->session->get('twitter_oauth'),
                $this->session->get('twitter_oauth_secret')
            );
            $access_token = $twitter->oauth("oauth/access_token", ["oauth_verifier" => $_REQUEST['oauth_verifier']]);
        }catch (\Exception $ex){
            $this->flash->addMessage('error', 'Hubo un problema al acceder a la API de Twitter. Intentalo más tarde.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $user = User::where('twitterId', $access_token['user_id'])->first();
        if(!$user){
            $this->session->set('twitter_id', $access_token['user_id']);
            $this->flash->addMessage('error', 'No has ligado ésta cuenta de Twitter a una cuenta del chat. Ingresa con las credenciales normales y ligala.');
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

    public function getCuentaCallback(Request $request, Response $response, $args)
    {
        $query = $request->getQueryParams();
        if($query['oauth_token'] !== $this->session->get('twitter_oauth')){
            $this->flash->addMessage('social-error', 'El token enviado por twitter y el local son diferentes.');
            return $this->withRedirectWithout($response, $this->router->pathFor('cuenta.main').'#formSocial');
        }
        $sett = $this->settings['twitter'];
        try {
            $twitter = new TwitterOAuth(
                $sett['consumer_key'],
                $sett['consumer_secret'],
                $this->session->get('twitter_oauth'),
                $this->session->get('twitter_oauth_secret')
            );
            $access_token = $twitter->oauth("oauth/access_token", ["oauth_verifier" => $_REQUEST['oauth_verifier']]);
        }catch(\Exception $ex){
            $this->flash->addMessage('social-error', 'Hubo un problema al acceder a la API de Twitter. Intentalo más tarde.');
            return $this->withRedirectWithout($response, $this->router->pathFor('cuenta.main').'#formSocial');
        }
        $user = User::find($this->session->get('user_id'));
        if(!$user){
            $this->flash->addMessage('error', '¡No has iniciado sesión!');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        /* Actualizar el ultimo inicio de sesión */
        $auth = [
            'oauth_token' => $access_token['oauth_token'],
            'oauth_token_secret' => $access_token['oauth_token_secret']
        ];
        $user->twitterId = $access_token['user_id'];
        $user->twitterToken = json_encode($auth);
        $user->ip = $request->getAttribute('ip_address');
        $user->save();
        $this->flash->addMessage('social', '¡Se ligo esta cuenta a Twitter!');
        return $this->withRedirectWithout($response, $this->router->pathFor('cuenta.main').'#formSocial');
    }

    public function getUnlink(Request $request, Response $response, $args)
    {
        $user = User::find($this->session->get('user_id'));
        if(!$user){
            $this->flash->addMessage('error', '¡No has iniciado sesión!');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        if(empty($user->twitterId)){
            $this->flash->addMessage('social-error', '¡Esta cuenta no está ligada a ninguna cuenta de Twitter!');
            return $this->withRedirectWithout($response, $this->router->pathFor('cuenta.main').'#formSocial');
        }
        // Guardamos token
        $user->twitterId = null;
        $user->twitterToken = null;
        $user->save();
        $this->flash->addMessage('social', 'Se desligo esta cuenta de Twitter.');
        return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
    }
}