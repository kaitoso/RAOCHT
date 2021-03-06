<?php
namespace App\Middleware;

use App\Model\AuthToken;
use App\Model\User;
use App\Security\Token;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Dflydev\FigCookies\FigRequestCookies as RequestCookies;

class SessionMiddleware extends Middleware{

    function __invoke(Request $request, Response $response, $next)
    {
        $cookie = RequestCookies::get($request, 'raoRemember');
        if(empty($this->container->session->get('user_id')) && !empty($cookie->getValue())){
            list($selector, $token) = explode(':', $cookie->getValue());
            $auth = AuthToken::where('selector', '=', $selector)->first();
            if($auth) {
                $token = hash_hmac('sha256', base64_decode($token), Token::KEY);
                if ($auth->ip !== $_SERVER['REMOTE_ADDR']) {
                    $auth->delete();
                    $request = RequestCookies::remove($request, 'raoRemember');
                }
                if (hash_equals($auth->token, $token)) {
                    $user = $auth->usuario()->first();
                    if($user->getBan){
                        $auth->delete();
                        $request = RequestCookies::remove($request, 'raoRemember');
                        $this->container->logger->info("BAN: {$user->user} intentó conectarse al chat por cookie.");
                        $this->container->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
                        return $this->withRedirectWithout($response, $this->container->router->pathFor('auth.login'));
                    }
                    $redis = $this->container->redis;
                    $this->container->session->set('user_id', $user->id);
                    $this->container->session->set('user', $user->user);
                    $this->container->session->set('rank', $user->rank);
                    /* Actualizar el auth */
                    $auth->last_used = date('Y-m-d H:i:s');
                    $auth->save();
                    /* Actualizar al usuario */
                    $user->ip = $_SERVER['REMOTE_ADDR'];
                    $user->lastLogin = date('Y-m-d H:i:s');
                    $user->save();
                    /* Publicar inicio de sesión */
                    $redis->setex($this->container->session->getSessionId(), 3600, json_encode(array(
                        'id' => $user->id,
                        'user' => $user->user,
                        'chatName' => $user->chatName,
                        'chatColor' => $user->chatColor,
                        'chatText' => $user->chatText,
                        'image' =>
                            $request->getUri()->getBaseUrl() . '/avatar/s/' . $user->image,
                        'rank' => $user->rank,
                    )));
                }
            }
        }
        $pathMain = $request->getUri()->getPath() === $this->container->router->pathFor('main.page');
        $pathPriv = $request->getUri()->getPath() === $this->container->router->pathFor('private.main');
        if(!empty($this->container->session->get('user_id')) && ($pathMain || $pathPriv)){
            $this->container->redis->delete($this->container->session->getSessionId());
            $user = User::find($this->container->session->get('user_id'));
            $userPro =  $user->getProfile;
            $this->container->redis->setex($this->container->session->getSessionId(), 3600, json_encode(array(
                'id' => $user->id,
                'user' => $user->user,
                'chatName' => $user->chatName,
                'chatColor' => $user->chatColor,
                'chatText' => $user->chatText,
                'image' =>
                    $request->getUri()->getBaseUrl().'/avatar/s/'.$user->image,
                'rank' => $user->rank,
                'profile' => [
                    'time' => $userPro->online_time,
                    'messages' => $userPro->messages
                ]
            )));
        }
        if(empty($this->container->session->get('token'))){
            $this->container->session->set('token',
                bin2hex($this->container->token->generateRandom(32))
            );
        }
        if(empty($this->container->session->get('token_key'))) {
            $this->container->session->set('token_key',
                $this->container->token->generateRandom(32)
            );
        }
        $this->container->view->getEnvironment()->addGlobal('csrf', [
            'field' => '<input class="hidden" type="hidden" name="raoToken" value="' .  $this->container->session->get('token') .'">',
            'token' => $this->container->session->get('token')
        ]);
        $response = $next($request, $response);
        return $response;
    }
}