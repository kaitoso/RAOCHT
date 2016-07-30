<?php
namespace App\Controller;

use App\Handler\Avatar;
use App\Handler\Email;
use App\Model\AuthToken;
use App\Model\User;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\SetCookie;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Dflydev\FigCookies\FigResponseCookies as RespCookies;
use Respect\Validation\Validator as v;
use App\Security\Token;

/**
 *
 */
class MainController extends BaseController
{

    public function index(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') === null) {
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        $user = User::find($this->session->get('user_id'));
        $permissions = json_decode($user->getRank->permissions);
        $chatConfig = require __DIR__.'/../Config/Chat.php';
        return $this->view->render($response, 'chat.twig', [
            'user' => $user,
            'permissions' => $permissions,
            'config' => $chatConfig
        ]);
      }

    public function error(Request $request, Response $response, $args)
    {
        if($this->flash->getMessage('error')){
            return $this->view->render($response, 'error.twig');
        }
        return $this->withRedirect($response, $this->router->pathFor('main.page'));
    }

    public function getLogin(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            return $this->withRedirect($response, $this->router->pathFor('main.page'));
        }
        if (!empty($this->session->get('redirect'))) {
            $redirect = $this->session->get('redirect');
            if($redirect === $this->router->pathFor('auth.login')){
                $redirect = $this->router->pathFor('main.page');
            }else if ($redirect === $this->router->pathFor('auth.signup')){
                $redirect = $this->router->pathFor('main.page');
            }
            $this->session->set('redirect', $redirect);
        }

        return $this->view->render($response, 'login.twig', [
            'redir' => $this->session->get('redirect')
        ]);
    }

    public function getLogout(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') === null) {
            return $this->redirectTo($response, $this->router->pathFor('auth.login'));
        }
        $cookie = FigRequestCookies::get($request, 'raoRemember');
        if(!empty($cookie->getValue())){
            list($selector, $token) = explode(':', $cookie->getValue());
            $auth = AuthToken::where('selector', '=', $selector)->first();
            if($auth) {
                $auth->delete();
                $response = RespCookies::remove($response, 'raoRemember');
                $response = RespCookies::expire($response, 'raoRemember');
            }
        }
        /* Borramos la llave de redis */
        $this->redis->delete($this->container->session->getSessionId());
        /* Borramos datos del usuario */
        $this->session->destroySession();
        return $this->withRedirect($response, $this->router->pathFor('auth.login'));
    }

    public function getSignUp(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $this->router->pathFor('main.page');
            return $this->withRedirect($response, $redirect);
        }
        return $this->view->render($response, 'registro.twig');
    }

    public function postLogin(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $this->router->pathFor('main.page');
            return $this->withRedirect($response, $redirect);
        }
        $validation = $this->validator->validate($request, [
            'user' => v::noWhitespace()->notEmpty()->alnum('_-')->length(4, 30),
            'password' => v::noWhitespace()->notEmpty(),
            'rememberMe' => v::boolVal(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);

        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        $inputUser = $request->getParam('user');
        $password = $request->getParam('password');
        $remember = $request->getParam('rememberMe');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }

        $user = User::where('user', $inputUser)->first();
        if(!$user){
            $this->session->addWithKey('errors', 'user', 'Este usuario no existe.');
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        $password = base64_encode(hash('sha256', $password, true));
        if (password_verify($password, $user->password)) {
            /* Establecer los datos de la sesión del usuario */
            $this->session->set('user_id', $user->id);
            $this->session->set('user', $user->user);
            $this->session->set('rank', $user->rank);
            /* Actualizar el ultimo inicio de sesión */
            if(!empty($this->session->get('fb_id'))){
                $user->facebookId = $this->session->get('fb_id');
            }
            $user->ip = $request->getAttribute('ip_address');
            $user->lastLogin = date('Y-m-d H:i:s');
            $user->save();
            /* Publicar la sesión al servidor */
            //$this->redis->publish('login', $this->session->getSessionId());
            $this->redis->setEx($this->session->getSessionId(), 3600, json_encode(array(
                'user_id' => $user->id,
                'user' => $user->user,
                'chatName' => $user->chatName,
                'chatColor' => $user->chatColor,
                'chatText' => $user->chatText,
                'image' =>
                    $request->getUri()->getBaseUrl().'/avatar/s/'.$user->image,
                'rank' => $user->rank,
            )));

            /* Guardar cookie */
            if ($remember === 'on') {
                $selector = base64_encode(Token::generateRandom(9));
                $token = Token::generateRandom(33);
                $date = new \DateTime('+4 week');
                /* Guardar token */
                $auth = new AuthToken;
                $auth->selector = $selector;
                $auth->token = hash_hmac('sha256', $token, Token::KEY);
                $auth->user_id = $user->id;
                $auth->expires = $date->format('Y-m-d H:i:s');
                $auth->last_used = date('Y-m-d H:i:s');
                $auth->ip = $request->getAttribute('ip_address');
                $auth->save();
                $response = RespCookies::set($response,  SetCookie::create('raoRemember')
                    ->withValue($selector . ':' . base64_encode($token))
                    ->withExpires($date)
                    ->withHttpOnly(true)
                    // Agregar dominio y path
                    /*
                     ->withPath('/')
                     ->withDomain('.example.com')
                     */
                );
            }
            $redir = $this->session->get('redirect');
            if (is_null($redir)) {
                $redir = $this->router->pathFor('main.page');
            } else {
                $this->session->set('redirect', null);
            }
            if ($request->isXhr()) {
                return $this->showJSONResponse($response, array(
                    'error' => true,
                    'message' => '',
                ), 200);
            } else {
                return $this->withRedirect($response, $redir);
            }
        } else {
            $this->flash->addMessage('error', 'El usuario o contraseña son incorrectos. Intenta de nuevo con otras credenciales.');
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
    }

    public function postSignUp(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $this->router->pathFor('main.page');
            return $this->withRedirect($response, $redirect);
        }
        $validation = $this->validator->validate($request, [
            'user' => v::noWhitespace()->notEmpty()->alnum('_-')->length(4, 30),
            'email' => v::noWhitespace()->notEmpty()->email(),
            'password' => v::noWhitespace()->notEmpty()->length(6),
            'rPassword' => v::noWhitespace()->notEmpty()->length(6),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);

        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response,  $this->router->pathFor('auth.signup'));
        }

        $inputUser = $request->getParam('user');
        $inputEmail = $request->getParam('email');
        $password = $request->getParam('password');
        $rPassword = $request->getParam('rPassword');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('auth.signup'));
        }
        if($password !== $rPassword){
            $this->flash->addMessage('error', 'Las contraseñas escritas son diferentes. Verifica por favor los campos.');
            return $this->withRedirect($response,  $this->router->pathFor('auth.signup'));
        }
        $user = User::where('user', $inputUser)
            ->orWhere('email', $inputEmail)
            ->first();
        if($user){
            $this->flash->addMessage('error', 'Este usuario/correo electrónico ya se encuentra registrado. Intente con otros.');
            return $this->withRedirect($response,  $this->router->pathFor('auth.signup'));
        }
        /* Crear imagen del usuario */
        $image = $inputUser. hash('sha256', time());
        Avatar::generateAvatar(
            __DIR__. '/../../../public/avatar/preduser.png',
            $image
        );
        /* Registrar al usuario en la base de datos */
        $newUser = new User();
        $newUser->email = $inputEmail;
        $newUser->password = password_hash(base64_encode(
            hash('sha256', $password, true)
        ), PASSWORD_BCRYPT, ['cost' => 10]);
        $newUser->user = $inputUser;
        $newUser->rank = 2;
        $newUser->chatName = $inputUser;
        $newUser->image = $image.'.png';
        $newUser->ip = $request->getAttribute('ip_address');
        $newUser->lastLogin = date('Y-m-d H:i:s');
        if(!empty($this->session->get('fb_id'))){
            $newUser->facebookId = $this->session->get('fb_id');
        }
        if(!empty($this->session->get('twitter_id'))){
            $newUser->twitterId = $this->session->get('twitter_id');
        }
        if(!empty($this->session->get('google_id'))){
            $newUser->googleId = $this->session->get('google_id');
        }
        $newUser->save();
        $email = new Email($this->email);
        $email->sendActivationEmail($this->view->getEnvironment(), $newUser);
        $this->flash->addMessage('success', '¡Te has registrado correctamente en el chat! Te hemos enviado un correo electrónico con los datos de activación.');
        return $this->withRedirect($response,  $this->router->pathFor('auth.login'));
    }
}
