<?php
namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Model\Ban;
use App\Model\User;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;

class Facebook extends BaseController
{

    public function getIndex(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            return $this->withRedirect($response, $this->router->pathFor('main.page'));
        }
        if(!empty($this->session->get('user_ban'))){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        $ban = Ban::where('ip', $request->getAttribute('ip_address'))->first();
        if($ban){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        $fbHelper = $this->fb->getRedirectLoginHelper();
        $url = $fbHelper->getLoginUrl(
            $request->getUri()->getBaseUrl().$this->router->pathFor('auth.facebook.callback'),
            ['email']
        );
        return $this->withRedirect($response, $url);
    }

    public function getCuentaLogin(Request $request, Response $response, $args)
    {
        $fbHelper = $this->fb->getRedirectLoginHelper();
        $url = $fbHelper->getLoginUrl(
            $request->getUri()->getBaseUrl().$this->router->pathFor('cuenta.facebook.callback'),
            ['email']
        );
        return $this->withRedirect($response, $url);
    }

    public function getFacebookCallback(Request $request, Response $response, $args)
    {
        $fbHelper = $this->fb->getRedirectLoginHelper();
        try {
            $accessToken = $fbHelper->getAccessToken();
        } catch(FacebookResponseException $e) {
            $this->flash->addMessage('error', 'Error al obtener el token: ' . $e->getMessage());
            return $this->withRedirect($response, $this->router->pathFor('main.error'));
        } catch(FacebookSDKException $e) {
            $this->flash->addMessage('error', 'Error al obtener el token: ' . $e->getMessage());
            return $this->withRedirect($response, $this->router->pathFor('main.error'));
        }
        if(empty($accessToken)){
            $this->flash->addMessage('error', 'Hubo un error al obtener el acceso a la cuenta de Facebook.');
            return $this->withRedirect($response, $this->router->pathFor('main.error'));
        }
        if(!$accessToken->isLongLived()){
            $oAuth2Client = $this->fb->getOAuth2Client();
            try {
                $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
            } catch (FacebookSDKException $e) {
                $this->flash->addMessage('error', 'Lo sentimos. Hubo un error al obtener el token de Facebook.');
                return $this->withRedirect($response, $this->router->pathFor('main.error'));
            }
        }
        try {
            $fbResp = $this->fb->get('/me', $accessToken->getValue() );
            $userNode = $fbResp->getGraphUser();
        } catch(FacebookResponseException $e) {
            // When Graph returns an error
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch(FacebookSDKException $e) {
            // When validation fails or other local issues
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }
        $this->session->set('fb_token', $accessToken->getValue());
        $this->session->set('fb_id', $userNode->getId());
        $user = User::where('facebookId', '=', $userNode->getId())->first();
        if(empty($user)){
            $this->container->flash->addMessage(
                'error',
                '¡Error! Al parecer no has ligado tu cuenta del chat con Facebook. 
                Intenta iniciar sesión con tus datos.'
            );
            return $this->withRedirectWithout($response, $this->container->router->pathFor('auth.login'));
        }
        // Guardar sesión
        $this->session->set('user_id', $user->id);
        $this->session->set('user', $user->user);
        $this->session->set('rank', $user->rank);
        // Guardar usuario
        $user->ip = $request->getAttribute('ip_address');
        $user->lastLogin = date('Y-m-d H:i:s');
        $user->facebookId = $userNode->getId();
        $user->facebookToken = $accessToken->getValue();
        $user->save();
        return $this->withRedirect($response, $this->router->pathFor('main.page'));
    }

    public function getFacebookCallbackLink(Request $request, Response $response, $args){
        $fbHelper = $this->fb->getRedirectLoginHelper();
        try {
            $accessToken = $fbHelper->getAccessToken();
        } catch(FacebookResponseException $e) {
            $this->flash->addMessage('social-error', 'Error al obtener el token: ' . $e->getMessage());
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
        } catch(FacebookSDKException $e) {
            $this->flash->addMessage('social-error', 'Error al obtener el token: ' . $e->getMessage());
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
        }
        if(empty($accessToken)){
            $this->flash->addMessage('social-error', $fbHelper->getErrorReason);
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
        }
        if(!$accessToken->isLongLived()){
            $oAuth2Client = $this->fb->getOAuth2Client();
            try {
                $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
            } catch (FacebookSDKException $e) {
                $this->flash->addMessage('social-error', 'Lo sentimos. Hubo un error al obtener el acceso de Facebook.');
                return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
            }
        }
        try {
            $fbResp = $this->fb->get('/me', $accessToken->getValue() );
            $userNode = $fbResp->getGraphUser();
        } catch(FacebookResponseException $e) {
            $this->flash->addMessage('social-error', 'Lo sentimos. Hubo un error al obtener el acceso de Facebook.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
        } catch(FacebookSDKException $e) {
            $this->flash->addMessage('social-error', 'Lo sentimos. Hubo un error al obtener el acceso de Facebook.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
        }
        $exists = User::where('facebookId', $userNode->getId())->count() !== 0;
        if($exists){
            $this->flash->addMessage('social-error', '¡Esta cuenta de Facebook ya está ligada a otra cuenta del chat!');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
        }
        $this->session->set('fb_token', $accessToken->getValue());
        $this->session->set('fb_id', $userNode->getId());
        $user = User::find($this->session->get('user_id'));
        // Guardamos token
        $user->facebookId = $userNode->getId();
        $user->facebookToken = $accessToken->getValue();
        $user->save();
        $this->flash->addMessage('social', '¡Se ligo esta cuenta a Facebook!');
        return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
    }

    public function getUnlink(Request $request, Response $response, $args)
    {
        $this->session->delete('fb_token');
        $this->session->delete('fb_id');
        $user = User::find($this->session->get('user_id'));
        if(empty($user->facebookId)){
            $this->flash->addMessage('social-error', '¡Esta cuenta no está ligada a ninguna cuenta de Facebook!');
            return $this->withRedirectWithout($response, $this->router->pathFor('cuenta.main').'#formSocial');
        }
        // Guardamos token
        $user->facebookId = null;
        $user->facebookToken = null;
        $user->save();
        $this->flash->addMessage('social', 'Se desligo esta cuenta de Facebook.');
        return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formSocial');
    }

}