<?php
namespace App\Controller;

use App\Handler\Avatar;
use App\Handler\Email;
use App\Model\AuthToken;
use App\Model\Ban;
use App\Model\PrivateMessage;
use App\Model\Rank;
use App\Model\User;
use App\Model\UserAchievements;
use App\Model\UserProfile;
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
        $ban = Ban::where('ip', $request->getAttribute('ip_address'))->first();
        if($ban){
            $hoy = date('Y-m-d H:i:s');
            if(strtotime($hoy) > $ban->date_ban) {
                $ban->delete();
            }else{
                $this->session->set('user_ban', true);
            }
            $this->session->delete('user_id');
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        $user = User::find($this->session->get('user_id'));
        $userRank = $user->getRank;
        $userPro = $user->getProfile;
        if(empty($userPro)){
            $userPro = new UserProfile();
            $userPro->user_id = $user->id;
            $userPro->save();
        }
        if(!empty($userRank->nextRank)){
            $nextTime = $userRank->nextTime ?: 0;
            $nextMessage = $userRank->nextMessage ?: 0;
            if($userPro->online_time >= $nextTime && $userPro->messages >= $nextMessage){
                $user->rank = $userRank->nextRank;
                $user->save();
                if(!empty($userRank->nextAchievement)){
                    $hasLogro = UserAchievements::where([
                        ['achievement_id', $userRank->nextAchievement],
                        ['user_id', $this->session->get('user_id')]
                    ])->first();
                    if(!$hasLogro){
                        $newLogro = new UserAchievements();
                        $newLogro->user_id = $this->session->get('user_id');
                        $newLogro->achievement_id = $userRank->nextAchievement;
                        $newLogro->save();
                    }
                }
                $userRank = Rank::find($userRank->nextRank);
            }
        }
        $pvs = PrivateMessage::where([
            ['to_id', $user->id],
            ['seen', 0]
        ])->count();
        $permissions = json_decode($userRank->permissions);
        $chatConfig = json_decode(file_get_contents(__DIR__.'/../Config/Chat.json'));
        return $this->view->render($response, 'chat.twig', [
            'user' => $user,
            'rank' => $userRank,
            'permissions' => $permissions,
            'config' => $chatConfig,
            'privates' => $pvs
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
        $ban = Ban::where('ip', $request->getAttribute('ip_address'))->first();
        if($ban){
            $hoy = date('Y-m-d H:i:s');
            if(strtotime($hoy) > $ban->date_ban) {
                $ban->delete();
                if(!empty($this->session->get('user_ban'))){
                    $this->session->delete('user_ban');
                }
                $ban = null;
            }else{
                $this->session->set('user_ban', true);
            }
        }else{
            $this->session->delete('user_ban');
        }
        $chatConfig = json_decode(file_get_contents(__DIR__.'/../Config/Chat.json'));
        return $this->view->render($response, 'login.twig', [
            'ban' => $ban,
            'config' => $chatConfig
        ]);
    }

    public function getLogout(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') === null) {
            if($request->isXhr()){
                return $this->showJSONResponse($response, ['error' => 'No has iniciado sesión.']);
            }
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
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
        if($request->isXhr()){
            return $this->showJSONResponse($response, ['success' => 'Se ha desconectado con éxito.']);
        }
        return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
    }

    public function getForgot(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            return $this->withRedirect($response, $this->router->pathFor('main.page'));
        }
        $ban = Ban::where('ip', $request->getAttribute('ip_address'))->first();
        if($ban){
            $hoy = date('Y-m-d H:i:s');
            if(strtotime($hoy) > $ban->date_ban) {
                $ban->delete();
            }else{
                $this->session->set('user_ban', true);
            }
            $this->session->delete('user_id');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $chatConfig = json_decode(file_get_contents(__DIR__.'/../Config/Chat.json'));
        $this->view->render($response, 'forgot.twig', ['config' => $chatConfig]);
    }

    public function getSignUp(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $this->router->pathFor('main.page');
            return $this->withRedirect($response, $redirect);
        }
        $ban = Ban::where('ip', $request->getAttribute('ip_address'))->first();
        if(!empty($this->session->get('user_ban')) || $ban){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $chatConfig = json_decode(file_get_contents(__DIR__.'/../Config/Chat.json'));
        return $this->view->render($response, 'registro.twig', ['config' => $chatConfig]);
    }

    public function getSignUpSocial(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $this->router->pathFor('main.page');
            return $this->withRedirect($response, $redirect);
        }
        if(empty($this->session->get('socialEmail'))){
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.signup'));
        }
        $ban = Ban::where('ip', $request->getAttribute('ip_address'))->first();
        if(!empty($this->session->get('user_ban')) || $ban){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $chatConfig = json_decode(file_get_contents(__DIR__.'/../Config/Chat.json'));
        return $this->view->render($response, 'registro-social.twig', [
            'socialEmail' => $this->session->get('socialEmail'),
            'config' => $chatConfig
        ]);
    }

    public function postLogin(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $this->router->pathFor('main.page');
            return $this->withRedirect($response, $redirect);
        }
        $ban = Ban::where('ip', $request->getAttribute('ip_address'))->first();
        if(!empty($this->session->get('user_ban')) || $ban){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $validation = $this->validator->validate($request, [
            'user' => v::noWhitespace()->notEmpty()->alnum('_-')->length(4, 30),
            'password' => v::noWhitespace()->notEmpty()->stringType(),
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

        if(!empty($this->session->get('user_ban'))){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
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
        if($user->getBan){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        if(!$user->activated){
            $this->flash->addMessage('error', '¡Aún no has activado tu cuenta! Revisa tu correo.
                El correo de activación puede estar en correos no deseados.');
            return $this->withRedirect($response, $this->router->pathFor('auth.login'));
        }
        $password = base64_encode(hash('sha256', $password, true));
        if (password_verify($password, $user->password)) {
            /* Establecer los datos de la sesión del usuario */
            $this->session->set('user_id', $user->id);
            $this->session->set('user', $user->user);
            $this->session->set('rank', $user->rank);
            /* Actualizar el ultimo inicio de sesión */
            $user->ip = $request->getAttribute('ip_address');
            $user->lastLogin = date('Y-m-d H:i:s');
            $user->save();
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
                $this->session->delete('socialEmail');
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
        $ban = Ban::where('ip', $request->getAttribute('ip_address'))->first();
        if(!empty($this->session->get('user_ban')) || $ban){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $validation = $this->validator->validate($request, [
            'user' => v::noWhitespace()->notEmpty()->alnum('_-')->length(4, 30),
            'email' => v::noWhitespace()->notEmpty()->email(),
            'password' => v::noWhitespace()->notEmpty()->length(6)->stringType(),
            'rPassword' => v::noWhitespace()->notEmpty()->length(6)->stringType(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        $pathFor = empty($this->session->get('socialEmail')) ? 'auth.signup' : 'auth.signup.social';
        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response,  $this->router->pathFor($pathFor));
        }

        $inputUser = $request->getParam('user');
        $inputEmail = $request->getParam('email');
        $password = $request->getParam('password');
        $rPassword = $request->getParam('rPassword');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor($pathFor));
        }
        if($password !== $rPassword){
            $this->flash->addMessage('error', 'Las contraseñas escritas son diferentes. Verifica por favor los campos.');
            return $this->withRedirect($response,  $this->router->pathFor($pathFor));
        }
        $user = User::where('user', $inputUser)
            ->orWhere('email', $inputEmail)
            ->first();
        if($user){
            if(empty($this->session->get('socialEmail'))){
                $this->flash->addMessage('error', 'Este usuario/correo electrónico ya se encuentra registrado. Intente con otros.');
            }else{
                $this->flash->addMessage('error', 'Este usuario ya se encuentra registrado. Intente con otros.');
            }
            return $this->withRedirect($response,  $this->router->pathFor($pathFor));
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
        $newUser->activated = empty($this->session->get('socialEmail')) ? 0 : 1;
        $newUser->lastLogin = date('Y-m-d H:i:s');
        if(!empty($this->session->get('fb_id'))){
            $newUser->facebookId = $this->session->get('fb_id');
            $newUser->activated = 1;
        }
        if(!empty($this->session->get('twitter_id'))){
            $newUser->twitterId = $this->session->get('twitter_id');
            $newUser->activated = 1;
        }
        if(!empty($this->session->get('google_id'))){
            $newUser->googleId = $this->session->get('google_id');
            $newUser->activated = 1;
        }
        $newUser->save();
        $profile = new UserProfile();
        $profile->user_id = $newUser->id;
        $profile->save();
        $email = new Email($this->email);
        if(empty($this->session->get('socialEmail'))){
            $email->sendActivationEmail($this->view->getEnvironment(), $newUser);
            $this->flash->addMessage('success', '¡Te has registrado correctamente en el chat!
            En unos momentos te estaremos enviando un correo electrónico con los datos de activación.
            Si éste no llega en menos de 10 minutos, revisa tu bandeja de correos no deseados o spam.');
            return $this->withRedirect($response,  $this->router->pathFor('auth.login'));
        }else{
            $email->sendWelcomeEmail($this->view->getEnvironment(), $newUser);
            // Iniciamos sesión
            $this->session->set('user_id', $newUser->id);
            $this->session->set('user', $newUser->user);
            $this->session->set('rank', $newUser->rank);
            $this->session->delete('socialEmail');
            return $this->withRedirect($response, $this->router->pathFor('main.page'));
        }
    }

    public function postForgot(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $this->router->pathFor('main.page');
            return $this->withRedirect($response, $redirect);
        }
        $ban = Ban::where('ip', $request->getAttribute('ip_address'))->first();
        if(!empty($this->session->get('user_ban')) || $ban){
            $this->flash->addMessage('error', '¡Estas expulsado! No puedes ingresar al chat.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $validation = $this->validator->validate($request, [
            'inputEmail' => v::noWhitespace()->notEmpty()->email(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);

        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response,  $this->router->pathFor('auth.forgot'));
        }

        $inputEmail = $request->getParam('inputEmail');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('auth.forgot'));
        }
        $user = User::where('email', $inputEmail)->first();
        if(!$user){
            $this->flash->addMessage('error', 'Éste correo electrónico es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('auth.forgot'));
        }

        $email = new Email($this->email);
        $email->sendForgotEmail($this->view->getEnvironment(), $user);
        $this->flash->addMessage('success', '¡Se ha enviado un correo electrónico con las instrucciones para recuperar tu contraseña! Llegará en unos momentos. Verfica el correo no deseado o de spam.');
        return $this->withRedirect($response, $this->router->pathFor('auth.login'));
    }
}
