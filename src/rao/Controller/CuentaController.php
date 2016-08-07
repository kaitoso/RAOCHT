<?php
namespace App\Controller;

use App\Handler\Avatar;
use App\Model\User;
use App\Model\UserAchievements;
use App\Model\UserProfile;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class CuentaController extends BaseController
{
    public function index(Request $request, Response $response, $args){
        if ($this->session->get('user_id') === null) {
            return $this->withRedirect($response, '/login');
        }
        $id = $this->session->get('user_id');
        $user = User::find($id);
        return $this->view->render($response, 'cuenta.twig', ['user' => $user]);
    }

    public function getLogros(Request $request, Response $response, $args)
    {
        if ($this->session->get('user_id') === null) {
            return $this->showJSONResponse($response, ['error' => 'No te has conectado al chat.']);
        }
        $validation = $this->validator->validateArgs($request, [
            'id' => v::optional(v::notEmpty()->intVal()->positive()),
        ]);
        if($validation->failed()){
            return $this->showJSONResponse($response, [
                'error' => 'El ID es incorrecto.'
            ]);
        }
        $id = $request->getAttribute('id');
        if(!empty($id) && $this->redis->exists('logro-'.$id)){
            $hasLogro = UserAchievements::where([
                ['achievement_id', $id],
                ['user_id', $this->session->get('user_id')]
            ])->first();
            if(!$hasLogro){
                $newLogro = new UserAchievements();
                $newLogro->user_id = $this->session->get('user_id');
                $newLogro->achievement_id = $id;
                $newLogro->save();
            }
        }
        $logros = UserAchievements::select('ach.name', 'ach.description', 'ach.image')
            ->join('achievements as ach', 'user_achievements.achievement_id', '=', 'ach.id')
            ->where([
                ['user_achievements.user_id', $this->session->get('user_id')],
                ['user_achievements.seen', 0]
            ])
            ->get();
        UserAchievements::where('user_id', $this->session->get('user_id'))
            ->update(['seen' => 1]);
        return $this->showJSONResponse($response, $logros->toArray());
    }

    public function postImagen(Request $request, Response $response, $args){
        $validation = $this->validator->validate($request, [
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson([
                    'error' => true,
                    'message' => 'El token está vacio. Intenta actualizar la página.'
                ]);
            }
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main'));
        }
        $files = $request->getUploadedFiles();
        if(empty($files['fileImage'])){
            return $response->withJson([
                'error' => true,
                'message' => 'No has subido ninguna imagen.'
            ]);
        }
        $avatarPath = __DIR__.'/../../../public/avatar';
        $id = $this->session->get('user_id');
        $user = User::find($id);
        try {
            $storage = new \Upload\Storage\FileSystem($avatarPath);
            $file = new \Upload\File('fileImage', $storage);
        }catch(\InvalidArgumentException $ex){
            return $response->withJson([
                'error' => true,
                'message' => 'No has subido ninguna imagen.'
            ]);
        }
        $file->setname($user->user . uniqid());
        $file->addValidations(array(
            new \Upload\Validation\Mimetype(array('image/png', 'image/gif', 'image/jpg', 'image/jpeg', 'image/gif')),
            new \Upload\Validation\Size('2M')
        ));
        $data = array(
            'name'      => $file->getName(),
            'fullName'  => $file->getNameWithExtension(),
            'extension' => $file->getExtension(),
            'mime'      => $file->getMimeType(),
            'size'      => $file->getSize()
        );
        try{
            $file->upload();
            $oldfile = $user->image;
            $user->image = $data['name'].'.png';
            $user->save();
            Avatar::generateAvatar($avatarPath.'/'.$data['fullName'], $data['name']);
            $resp['error'] = false;
            $resp['image'] = $request->getUri()->getBaseUrl().'/avatar/b/'. $data['name'] .'.png';
            /* Pubsub nueva imagen */
            $this->redis->publish('update-image', json_encode([
                'id' => $this->session->getSessionId(),
                'image' => $resp['image']
            ]));
            /* Remover imagenes */
            if(file_exists($avatarPath.'/b/'.$oldfile)){
                unlink($avatarPath.'/b/'.$oldfile);
            }
            if(file_exists($avatarPath.'/s/'.$oldfile)){
                unlink($avatarPath.'/s/'.$oldfile);
            }
            unlink($avatarPath.'/'.$data['fullName']);
        }catch(\Exception $e){
            $resp['message'] = 'Hubo un error al actualizar tu imagen, verifica si la imagen es válida';
            //$resp['message'] = "[{$e->getFile()}][{$e->getLine()}] ". $e->getMessage();
            echo $e;
        }finally{
            $this->showJSONResponse($response, $resp);
        }
    }

    public function postAbout(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'inputAbout' => v::stringType()->length(null, 1000),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formAbout');
        }
        $inputAbout = $request->getParam('inputAbout');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formAbout');
        }
        $userProfile = UserProfile::find($this->session->get('user_id'));
        $userProfile->about_me = $inputAbout;
        $userProfile->save();
        $this->flash->addMessage('about-change', '¡Se ha cambiado tu información de tu perfil con éxito');
        return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#formAbout');
    }

    public function putChatInfo(Request $request, Response $response, $args){
        $validation = $this->validator->validate($request, [
            'chatName' => v::stringType()->notEmpty()->length(4, 50),
            'chatColor' => v::noWhitespace()->notEmpty()->hexRgbColor(),
            'chatTexto' => v::noWhitespace()->notEmpty()->hexRgbColor(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#chatForm');
        }
        $chatName = $request->getParam('chatName');
        $chatColor = $request->getParam('chatColor');
        $chatTexto = $request->getParam('chatTexto');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#chatForm');
        }

        $id = $this->session->get('user_id');
        $user = User::find($id);
        $user->chatName = $chatName;
        $user->chatColor = str_replace('#', '', $chatColor);
        $user->chatText = str_replace('#', '', $chatTexto);
        $user->save();
        // Pubsub
        $this->redis->publish('update-chat', json_encode([
            'id' => $this->session->getSessionId(),
            'chatName' => $user->chatName,
            'chatColor' => $user->chatColor,
            'chatText' => $user->chatText
        ]));
        $this->flash->addMessage('chatInfo-change', 'Se han cambiado tu información correctamente!');
        return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#chatForm');
    }

    public function putPassword(Request $request, Response $response, $args){
        $validation = $this->validator->validate($request, [
            'currentPassword' => v::noWhitespace()->notEmpty()->length(6),
            'newPassword' => v::noWhitespace()->notEmpty()->length(6),
            'newRPassword' => v::noWhitespace()->notEmpty()->length(6),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#passwordForm');
        }
        $currentPassword = $request->getParam('currentPassword');
        $newPassword = $request->getParam('newPassword');
        $newRPassword = $request->getParam('newRPassword');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('password-change-error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#passwordForm');
        }
        if($newPassword !== $newRPassword){
            $this->flash->addMessage('password-change-error', 'Las nuevas contraseñas ingresadas son diferentes. Ingresa de nuevo las nuevas contraseñas.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#passwordForm');
        }
        $id = $this->session->get('user_id');
        $user = User::find($id);
        $nPass = $newPassword;
        $currentPassword = base64_encode(hash('sha256', $currentPassword, true));
        $newPassword = base64_encode(hash('sha256', $newPassword, true));
        if(password_verify($currentPassword, $user->password)){
            if(password_verify($newPassword, $user->password)){
                $this->flash->addMessage('password-change-error', 'La nueva contraseña es la misma que la contraseña actual. Intenta con otra.');
                return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#passwordForm');
            }
            $user->password = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
            $user->save();
            $this->flash->addMessage('password-change', '¡Tus datos se han cambiado correctamente!');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#passwordForm');
        }else{
            $this->flash->addMessage('password-change-error', 'La contraseña actual es inválida.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#passwordForm');
        }
    }

    public function putEmail(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'currentPassword' => v::noWhitespace()->notEmpty()->length(6),
            'newEmail' => v::noWhitespace()->notEmpty()->email(),
            'newREmail' => v::noWhitespace()->notEmpty()->email(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#correoForm');
        }
        $currentPassword = $request->getParam('currentPassword');
        $inputEmail = $request->getParam('newEmail');
        $inputREmail = $request->getParam('newREmail');
        $inputToken =  $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('email-change-error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#correoForm');
        }
        if($inputEmail !== $inputREmail){
            $this->flash->addMessage('email-change-error', 'El nuevo correo ingreso es diferente al de confirmación. Ingresa los correos electrónicos correctamente.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#correoForm');
        }
        $existsEmail = User::where('email', $inputEmail)->first();
        if($existsEmail){
            $this->flash->addMessage('email-change-error', 'Este correo electrónico ya se encuentra registrado. Intenta con otro.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#correoForm');
        }
        $id = $this->session->get('user_id');
        $user = User::find($id);
        $currentPassword = base64_encode(hash('sha256', $currentPassword, true));
        if(password_verify($currentPassword, $user->password)){
            $user->email = $inputEmail;
            $user->save();
            $this->flash->addMessage('email-change', '¡Tu correo electrónico ha cambiado correctamente!');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#correoForm');
        }else{
            $this->flash->addMessage('email-change-error', 'La contraseña actual es inválida.');
            return $this->withRedirect($response, $this->router->pathFor('cuenta.main').'#correoForm');
        }
    }
}