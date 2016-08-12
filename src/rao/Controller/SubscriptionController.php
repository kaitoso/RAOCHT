<?php

namespace App\Controller;

use App\Model\AuthToken;
use Respect\Validation\Validator as v;
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
        if(!$token->activation){
            $this->logger->notice("[Activation] Token usado para activación: {$inputToken}");
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

    public function getPassword(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') !== null) {
            return $this->withRedirect($response, $this->router->pathFor('main.page'));
        }
        $inputToken = $args['token'];
        $token = AuthToken::where('token', $inputToken)->first();
        if(!$token){
            $this->logger->notice("[Activation] Token no registrado: {$inputToken}");
            $this->flash->addMessage('error', 'Hubo un problema al obtener la verficación del cambio. Envíanos un mensaje por facebook con el nombre de tu cuenta.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        if(!$token->activation){
            $this->logger->notice("[Activation] Token usado para activación: {$inputToken}");
            $this->flash->addMessage('error', 'Hubo un problema al obtener la verficación del cambio. Envíanos un mensaje por facebook con el nombre de tu cuenta.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $user = $token->usuario;
        if(!$user->activated){
            $this->logger->notice("[Activation] Usuario no activado: {$inputToken}");
            $this->flash->addMessage('error', 'Al parecer no has activado tu cuenta.');
            $token->delete();
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        return $this->view->render($response, 'forgot-pass.twig');
    }

    public function postPassword(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'token' => v::notEmpty()->stringType()
        ]);
        $validation = $this->validator->validate($request, [
            'password' => v::noWhitespace()->notEmpty()->length(6)->stringType(),
            'rPassword' => v::noWhitespace()->notEmpty()->length(6)->stringType(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('email.password', $args));
        }
        $argToken = $request->getAttribute('token');
        $newPassword = $request->getParam('password');
        $newRPassword = $request->getParam('rPassword');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('email.password', $args));
        }
        if($newPassword !== $newRPassword){
            $this->flash->addMessage('error', 'Las nuevas contraseñas ingresadas son diferentes. Ingresa de nuevo las nuevas contraseñas.');
            return $this->withRedirect($response, $this->router->pathFor('email.password', $args));
        }
        $token = AuthToken::where('token', $argToken)->first();
        if(!$token){
            $this->logger->notice("[Activation] Token no registrado: {$argToken}");
            $this->flash->addMessage('error', 'Hubo un problema al obtener la verficación del cambio. Envíanos un mensaje por facebook con el nombre de tu cuenta.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        if(!$token->activation){
            $this->logger->notice("[Activation] Token usado para activación: {$argToken}");
            $this->flash->addMessage('error', 'Hubo un problema al obtener la verficación del cambio. Envíanos un mensaje por facebook con el nombre de tu cuenta.');
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $user = $token->usuario;
        if(!$user->activated){
            $this->logger->notice("[Activation] Usuario no activado: {$argToken}");
            $this->flash->addMessage('error', 'Al parecer no has activado tu cuenta.');
            $token->delete();
            return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
        }
        $newPassword = base64_encode(hash('sha256', $newPassword, true));
        $user->password = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
        $user->save();
        $token->delete();
        $this->flash->addMessage('success', '¡Tu contraseña ha sido actualizada correctamente!');
        return $this->withRedirectWithout($response, $this->router->pathFor('auth.login'));
    }
}