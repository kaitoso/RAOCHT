<?php
namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Model\Achievement;
use App\Model\User;
use App\Model\UserAchievements;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class LogroController extends BaseController
{

    public function getIndex(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'admin/logro.twig');
    }

    public function getNew(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'admin/logro-new.twig');
    }

    public function getUpdate(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->intVal()->positive(),
        ]);
        if($validation->failed()){
            $this->flash->addMessage('error', 'No ingresaste una identificación.');
            return $this->withRedirect($response, $this->router->pathFor('admin.logro'));
        }
        $logro = Achievement::find($args['id']);
        if(!$logro){
            $this->flash->addMessage('error', '¡Éste logro no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.logro'));
        }
        return $this->view->render($response, 'admin/logro-update.twig', [
            'logro' => $logro
        ]);
    }

    public function postNew(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'inputName' => v::notEmpty()->alnum(',;.:-_^*+-/¡!¿?()áéíóúÁÉÍÓÚñÑ')->length(1, 50),
            'inputDesc' =>  v::notEmpty()->alnum(',;.:-_^*+-/¡!¿?()áéíóúÁÉÍÓÚñÑ')->length(1, 100),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);

        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.logro.new'));
        }
        $files = $request->getUploadedFiles();
        $inputName = $request->getParam('inputName');
        $inputDesc = $request->getParam('inputDesc');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.logro.new'));
        }
        if(empty($files['fileImage']) || empty($files['fileImage']->file)){
            $this->flash->addMessage('error', '¡No has subido ninguna imágen!');
            return $this->withRedirect($response, $this->router->pathFor('admin.logro.new'));
        }
        $smiliePath = __DIR__.'/../../../../public/achievements';
        try {
            $storage = new \Upload\Storage\FileSystem($smiliePath);
            $file = new \Upload\File('fileImage', $storage);
        }catch(\InvalidArgumentException $ex){
            return $response->withJson([
                'error' => true,
                'message' => 'No has subido ninguna imagen.'
            ]);
        }
        $file->setname(uniqid());
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
            $logro = new Achievement();
            $logro->name = $inputName;
            $logro->description = $inputDesc;
            $logro->image = $data['fullName'];
            $logro->save();
        }catch(\Exception $e){
            $this->flash->addMessage('error', 'Hubo un error: ' . $e->getMessage());
            return $this->withRedirect($response, $this->router->pathFor('admin.logro.new'));
        }finally{
            $this->flash->addMessage('success', '¡Se ha agregado el nuevo logro con éxito!');
            return $this->withRedirect($response, $this->router->pathFor('admin.logro'));
        }
    }

    public function postUser(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'logro_id' => v::notEmpty()->notEmpty()->intVal()->positive(),
            'user_id' => v::notEmpty()->notEmpty()->intVal()->positive(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
           return $this->showJSONResponse($response, $this->session->get('errors'));
        }
        $inputLogro = $request->getParam('logro_id');
        $inputUser = $request->getParam('user_id');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            return $this->showJSONResponse($response, ['error' => 'Éste token es inválido']);
        }

        $logro = Achievement::find($inputLogro);
        if(!$logro){
            return $this->showJSONResponse($response, ['error' => 'Éste logro no existe.']);
        }
        $user = User::find($inputUser);
        if(!$user){
            return $this->showJSONResponse($response, ['error' => 'Éste usuario no existe.']);
        }
        $userLogro = UserAchievements::where([
            ['user_id', $inputUser],
            ['achievement_id', $inputLogro]
        ])->first();
        if($userLogro){
            return $this->showJSONResponse($response, ['error' => 'Éste usuario ya tiene éste logro.']);
        }

        $newLogro = new UserAchievements();
        $newLogro->user_id = $inputUser;
        $newLogro->achievement_id = $inputLogro;
        $newLogro->save();
        /* Pub new logro user */
        $this->redis->publish('user-achievement', json_encode([
            'user_id' => $inputUser
        ]));
        return $this->showJSONResponse($response, ['success' => '¡Se ha agregado el logro al usuario!']);
    }

    public function postGlobal(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'logro_id' => v::notEmpty()->notEmpty()->intVal()->positive(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            return $this->showJSONResponse($response, $this->session->get('errors'));
        }
        $inputLogro = $request->getParam('logro_id');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            return $this->showJSONResponse($response, ['error' => 'Éste token es inválido']);
        }

        $logro = Achievement::find($inputLogro);
        if(!$logro){
            return $this->showJSONResponse($response, ['error' => 'Éste logro no existe.']);
        }
        /* Pub new logro user */
        if($this->redis->exists('logro-'.$inputLogro)){
            return $this->showJSONResponse($response, [
                'error' => 'Éste logro ya está asignado a los usuarios conectos. Intente de nuevo en 5 minutos.'
            ]);
        }
        $this->redis->setEx('logro-'.$inputLogro, 300, json_encode(array(
            'id' => $this->session->getSessionId(),
            'logro_id' => $inputLogro
        )));
        $this->redis->publish('global-achievement', json_encode([
            'id' => $this->session->getSessionId(),
            'logro_id' => $inputLogro
        ]));
        return $this->showJSONResponse($response, ['success' => '¡Se ha enviado el logro globalmente!']);
    }

    public function putUpdate(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'inputName' => v::notEmpty()->alnum(',;.:-_^*+-/¡!¿?()áéíóúñ')->length(1, 50),
            'inputDesc' =>  v::notEmpty()->alnum(',;.:-_^*+-/¡!¿?()áéíóúñ')->length(1, 100),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);

        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.logro.update', $args));
        }
        $files = $request->getUploadedFiles();
        $inputName = $request->getParam('inputName');
        $inputDesc = $request->getParam('inputDesc');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.logro.update', $args));
        }
        if(empty($files['fileImage'])){
            $this->flash->addMessage('error', '¡No has subido ninguna imágen!');
            return $this->withRedirect($response, $this->router->pathFor('admin.logro.update', $args));
        }
        $currentLogro = Achievement::find($args['id']);
        if(!$currentLogro){
            $this->flash->addMessage('error', 'Éste logro no existe.');
            return $this->withRedirect($response, $this->router->pathFor('admin.logro'));
        }
        $logro = [
            'changed' => false,
            'file' => $currentLogro->image
        ];
        $achivementPath = __DIR__.'/../../../../public/achievements';
        if(!empty($files['fileImage']->file)){
            try {
                $storage = new \Upload\Storage\FileSystem($achivementPath);
                $file = new \Upload\File('fileImage', $storage);
            }catch(\InvalidArgumentException $ex){
                return $response->withJson([
                    'error' => true,
                    'message' => 'No has subido ninguna imagen.'
                ]);
            }
            $file->setname(uniqid());
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
                $currentLogro->image = $data['fullName'];
                $logro['changed'] = true;
            }catch(\Exception $e){
                $this->flash->addMessage('error', 'Hubo un error: ' . $e->getMessage());
                return $this->withRedirect($response, $this->router->pathFor('admin.logro.update', $args));
            }
        }
        $currentLogro->name = $inputName;
        $currentLogro->description = $inputDesc;
        $currentLogro->save();

        if($logro['changed'] && file_exists($achivementPath.'/'.$logro['file'])){
            unlink($achivementPath.'/'.$logro['file']);
        }
        $this->flash->addMessage('success', '¡Se ha agregado el nuevo logro con éxito!');
        return $this->withRedirect($response, $this->router->pathFor('admin.logro'));
    }

    public function deleteUser(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'logro_id' => v::notEmpty()->notEmpty()->intVal()->positive(),
            'user_id' => v::notEmpty()->notEmpty()->intVal()->positive(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            return $this->showJSONResponse($response, $this->session->get('errors'));
        }
        $inputLogro = $request->getParam('logro_id');
        $inputUser = $request->getParam('user_id');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            return $this->showJSONResponse($response, ['error' => 'Éste token es inválido']);
        }

        $logro = Achievement::find($inputLogro);
        if(!$logro){
            return $this->showJSONResponse($response, ['error' => 'Éste logro no existe.']);
        }
        $user = User::find($inputUser);
        if(!$user){
            return $this->showJSONResponse($response, ['error' => 'Éste usuario no existe.']);
        }
        $userLogro = UserAchievements::where([
            ['user_id', $inputUser],
            ['achievement_id', $inputLogro]
        ])->first();
        if(!$userLogro){
            return $this->showJSONResponse($response, ['error' => 'Éste usuario no tiene éste logro']);
        }
        $userLogro->delete();
        return $this->showJSONResponse($response, ['success' => '¡Se ha agregado el logro al usuario!']);
    }

    public function deleteLogro(Request $request, Response $response, $args)
    {

        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);

        if($validation->failed() || $validationGet->failed()){
            return $this->showJSONResponse($response, ['error' => $this->session->get('errors')]);
        }

        $inputToken = $request->getParam('raoToken');
        if($this->session->get('token') !== $inputToken){
            return $this->showJSONResponse($response, ['error' => 'Éste token es inválido.']);
        }
        $logro = Achievement::find($args['id']);
        if(!$logro){
            return $this->showJSONResponse($response, ['error' => 'Éste logro no existe.']);
        }

        $logro->delete();
        return $this->showJSONResponse($response, ['success' => 'Se ha eliminado el logro correctamente']);
    }
}