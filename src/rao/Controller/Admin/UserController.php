<?php
namespace App\Controller\Admin;


use App\Controller\BaseController;
use App\Model\Rank;
use App\Model\User;
use App\Handler\Avatar;
use App\Model\UserProfile;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
class UserController extends BaseController
{

    public function getIndex(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'admin/user.twig');
    }

    public function getNew(Request $request, Response $response, $args)
    {
        $rangos = Rank::get();
        return $this->view->render($response, 'admin/user-new.twig', ['rank' => $rangos]);
    }

    public function getUpdate(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        if($validation->failed()){
            $this->flash->addMessage('error', 'No ingresaste una identificación.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }
        if(in_array($args['id'], [1, 2])) {
            $this->flash->addMessage('error', 'Este usuario no se puede modificar. Pertenece a los dos usuarios por default.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }

        $user = User::find($args['id']);
        if(!$user){
            $this->flash->addMessage('error', '¡Éste usuario no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }
        $rangos = Rank::get();
        return $this->view->render($response, 'admin/user-update.twig', ['upUser' => $user, 'rank' => $rangos]);
    }

    public function postNew(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'inputUser' => v::noWhitespace()->notEmpty()->alnum('_-')->length(4, 30),
            'inputEmail' => v::noWhitespace()->notEmpty()->email(),
            'inputPassword' => v::noWhitespace()->notEmpty()->length(6),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);

        if($validation->failed()){
            return $this->withRedirect($response,  $this->router->pathFor('admin.user.new'));
        }

        $inputUser = $request->getParam('inputUser');
        $inputEmail = $request->getParam('inputEmail');
        $password = $request->getParam('inputPassword');
        $inputToken = $request->getParam('raoToken');
        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.new'));
        }

        $user = User::where('user', $inputUser)
            ->orWhere('email', $inputEmail)
            ->first();
        if($user){
            $this->flash->addMessage('error', 'Este usuario/correo electrónico ya se encuentra registrado. Intente con otros.');
            return $this->withRedirect($response,  $this->router->pathFor('admin.user.new'));
        }
        /* Crear imagen del usuario */
        $image = $inputUser. hash('sha256', time());
        Avatar::generateAvatar(
            __DIR__. '/../../../../public/avatar/preduser.png',
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
        $newUser->ip = '127.0.0.1';
        $newUser->lastLogin = date('Y-m-d H:i:s');
        $newUser->save();
        /* Create profile */
        $profile = new UserProfile();
        $profile->user_id = $newUser->id;
        $profile->save();
        /* Send response */
        $this->flash->addMessage('success', "¡Se ha registrado correctamente al usuario {$$newUser->user} con éxito!");
        return $this->withRedirect($response,  $this->router->pathFor('admin.user'));
    }

    public function postImage(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $response->withJson([
                    'error' => 'El token está vacio. Intenta actualizar la página.'
                ]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]));
        }
        $inputToken = $request->getParam('raoToken');
        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]));
        }
        $files = $request->getUploadedFiles();
        if(empty($files['fileImage']) || empty($files['fileImage']->file)){
            return $this->showJSONResponse($response,[
                'error' => true,
                'message' => 'No has subido ninguna imagen.'
            ]);
        }
        $avatarPath = __DIR__.'/../../../../public/avatar';
        $user = User::find($args['id']);
        if(!$user){
            if($request->isXhr()){
                return $response->withJson([
                    'error' => 'Este usuario no existe.'
                ]);
            }
            $this->flash->addMessage('error', '¡Éste usuario no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }
        $storage = new \Upload\Storage\FileSystem($avatarPath);
        $file = new \Upload\File('fileImage', $storage);
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
            $user->image = $file->getName().'.png';
            $user->save();
            Avatar::generateAvatar($avatarPath.'/'.$data['fullName'], $data['name']);
            $resp['error'] = false;
            $resp['image'] = $request->getUri()->getBaseUrl().'/avatar/b/'. $data['name'] .'.png';
            /* Pubsub nueva imagen */
            $this->redis->publish('admin-update-image', json_encode([
                'id' => $user->id,
                'image' => $request->getUri()->getBaseUrl().'/avatar/s/'. $data['name'] .'.png'
            ]));
            /* Remover imagenes */
            if(file_exists($avatarPath.'/b/'.$oldfile)){
                unlink($avatarPath.'/b/'.$oldfile);
            }
            if(file_exists($avatarPath.'/s/'.$oldfile)){
                unlink($avatarPath.'/s/'.$oldfile);
            }
            unlink($avatarPath.'/'.$file->getNameWithExtension());
        }catch(\Exception $e){
            $resp['message'] = 'Hubo un error al actualizar tu imagen, verifica si la imagen es válida';
        }finally{
            $this->showJSONResponse($response, $resp);
        }
    }

    public function putGeneral(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'inputName' => v::noWhitespace()->notEmpty()->alnum('_')->length(4, 30),
            'inputRango' => v::intVal()->notEmpty()->positive(),
            'inputActivated' => v::optional(v::boolVal()->notEmpty()),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect(
                $response,
                $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#generalForm'
            );
        }
        $inputName = $request->getParam('inputName');
        $inputRank = $request->getParam('inputRango');
        $inputActi = $request->getParam('inputActivated');
        $inputToken = $request->getParam('raoToken');
        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('general-error', 'Éste token es inválido.');
            return $this->withRedirect(
                $response,
                $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#generalForm'
            );
        }

        $rankExist = Rank::find($inputRank);
        if(!$rankExist){
            $this->flash->addMessage('general-error', 'Este rango no existe. Verifica el campo.');
            return $this->withRedirect(
                $response,
                $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#generalForm'
            );
        }
        if($rankExist->id === 1 && $this->session->get('rank') !== 1){
            $this->flash->addMessage('general-error', 'Sólo un administrador puede poner de usuario a otro administrador ' .$this->session->get('rank'));
            return $this->withRedirect(
                $response,
                $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#generalForm'
            );
        }
        $user = User::find($args['id']);
        if(!$user){
            $this->flash->addMessage('error', '¡Éste usuario no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }
        $userExist = User::where('user', $inputName)->first();
        if($userExist && $userExist->id !== $user->id){
            $this->flash->addMessage('general-error', 'Este nombre de usuario ya existe. Elije otro.');
            return $this->withRedirect(
                $response,
                $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#generalForm'
            );
        }

        $user->user = $inputName;
        $user->rank = $inputRank;
        $user->activated = $inputActi === 'on';
        $user->save();

        /* Publicar cambio de usuario */
        $this->redis->publish('admin-update-user', json_encode([
            'id' => $user->id,
            'user' => $user->user,
            'rank' => $user->rank
        ]));

        $this->flash->addMessage('general', '¡Se han cambiado los datos de este usuario con éxito!');
        return $this->withRedirect(
            $response,
            $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#generalForm'
        );
    }

    public function putPefilInfo(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'inputAbout' => v::stringType()->length(null, 1000),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#formAbout');
        }

        $inputUser = $request->getAttribute('id');
        $inputAbout = $request->getParam('inputAbout');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#formAbout');
        }

        $userProfile = UserProfile::find($inputUser);
        $userProfile->about_me = $inputAbout;
        $userProfile->save();
        $this->flash->addMessage('about-change', '¡Se ha cambiado la información del perfil con éxito');
        return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#formAbout');
    }

    public function putChatInfo(Request $request, Response $response, $args){
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'chatName' => v::stringType()->notEmpty()->length(4, 50),
            'chatColor' => v::noWhitespace()->notEmpty()->hexRgbColor(),
            'chatTexto' => v::noWhitespace()->notEmpty()->hexRgbColor(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#chatForm');
        }
        $chatName = $request->getParam('chatName');
        $chatColor = $request->getParam('chatColor');
        $chatTexto = $request->getParam('chatTexto');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#chatForm');
        }

        $user = User::find($args['id']);
        if(!$user){
            $this->flash->addMessage('error', '¡Éste usuario no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }
        $user->chatName = $chatName;
        $user->chatColor = str_replace('#', '', $chatColor);
        $user->chatText = str_replace('#', '', $chatTexto);
        $user->save();
        // Pubsub
        $this->redis->publish('admin-update-chat', json_encode([
            'id' => $this->session->getSessionId(),
            'chatName' => $user->chatName,
            'chatColor' => $user->chatColor,
            'chatText' => $user->chatText
        ]));
        $this->flash->addMessage('chatInfo-change', 'Se han cambiado tu información correctamente!');
        return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#chatForm');
    }

    public function putPassword(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'newPassword' => v::noWhitespace()->notEmpty()->length(6),
            'newRPassword' => v::noWhitespace()->notEmpty()->length(6),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#passwordForm');
        }
        $newPassword = $request->getParam('newPassword');
        $newRPassword = $request->getParam('newRPassword');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('password-change-error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#passwordForm');
        }
        if($newPassword !== $newRPassword){
            $this->flash->addMessage('password-change-error', 'Las nuevas contraseñas ingresadas son diferentes. Ingresa de nuevo las nuevas contraseñas.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#passwordForm');
        }
        $user = User::find($args['id']);
        if(!$user){
            $this->flash->addMessage('error', '¡Éste usuario no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }
        $newPassword = base64_encode(hash('sha256', $newPassword, true));
        if(password_verify($newPassword, $user->password)){
            $this->flash->addMessage('password-change-error', 'La nueva contraseña es la misma que la contraseña actual. Intenta con otra.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#passwordForm');
        }
        $user->password = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
        $user->save();
        $this->flash->addMessage('password-change', "¡Los datos del usuario {$user->user} se han cambiado correctamente!");
        return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#passwordForm');
    }

    public function putEmail(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'newEmail' => v::noWhitespace()->notEmpty()->email(),
            'newREmail' => v::noWhitespace()->notEmpty()->email(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#correoForm');
        }
        $inputEmail = $request->getParam('newEmail');
        $inputREmail = $request->getParam('newREmail');
        $inputToken =  $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('email-change-error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#correoForm');
        }
        if($inputEmail !== $inputREmail){
            $this->flash->addMessage('email-change-error', 'El nuevo correo ingreso es diferente al de confirmación. Ingresa los correos electrónicos correctamente.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#correoForm');
        }
        $existsEmail = User::where('email', $inputEmail)->first();
        if($existsEmail){
            $this->flash->addMessage('email-change-error', 'Este correo electrónico ya se encuentra registrado. Intenta con otro.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#correoForm');
        }
        $user = User::find($args['id']);
        if(!$user){
            $this->flash->addMessage('error', '¡Éste usuario no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }
        $user->email = $inputEmail;
        $user->save();
        $this->flash->addMessage('email-change', "¡Los datos del usuario {$user->user} se han cambiado correctamente!");
        return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#correoForm');
    }

    public function deleteUser(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#correoForm');
        }
        $inputToken =  $request->getParam('raoToken');
        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('email-change-error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user.update', ['id' => $args['id']]).'#correoForm');
        }

        $user = User::find($args['id']);
        if(!$user){
            $this->flash->addMessage('error', '¡Éste usuario no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }

        if(in_array($user->id, [1,2])){
            $this->flash->addMessage('error', 'No puedes eliminar este usuario. Pertenece a los usuarios por default.');
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }
        $user = User::find($args['id']);
        $rank = $user->getRank;
        $name = $user->user;
        if($rank->immunity){
            $this->flash->addMessage('error', "El usuario {$user->user} tiene inmunidad por su rango de \"{$rank->name}\".");
            return $this->withRedirect($response, $this->router->pathFor('admin.user'));
        }
        $avatarPath = __DIR__.'/../../../../public/avatar';
        if(file_exists($avatarPath.'/b/'.$user->image)){
            unlink($avatarPath.'/b/'.$user->image);
        }
        if(file_exists($avatarPath.'/s/'.$user->image)){
            unlink($avatarPath.'/s/'.$user->image);
        }
        $user->delete();
        $this->flash->addMessage('success', "El usuario {$name} ha sido borrado correctamente.");
        return $this->withRedirect($response, $this->router->pathFor('admin.user'));
    }
}