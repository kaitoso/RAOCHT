<?php
namespace App\Controller\Admin;


use App\Controller\BaseController;
use App\Handler\ClientCache;
use App\Model\Smilie;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Validator as v;

class SmilieController extends BaseController
{
    public function getIndex(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'admin/smilie.twig');
    }
    public function getNew(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'admin/smilie-new.twig');
    }
    public function getUpdate(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        if($validation->failed()){
            $this->flash->addMessage('error', 'No ingresaste una identificación.');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie'));
        }
        $smilie = Smilie::find($args['id']);
        if(!$smilie){
            $this->flash->addMessage('error', '¡Éste smilie no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie'));
        }
        return $this->view->render($response, 'admin/smilie-update.twig', [
            'smilie' => $smilie
        ]);
    }
    public function postNew(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'inputCode' => v::noWhitespace()->notEmpty()->alnum()->length(1, 10),
            'inputUrl' => v::optional(v::noWhitespace()->imageUrl()),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);

        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.new'));
        }
        $files = $request->getUploadedFiles();
        $inputCode = $request->getParam('inputCode');
        $inputUrl = $request->getParam('inputUrl');
        $inputToken = $request->getParam('raoToken');
        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.new'));
        }
        if(empty($files['fileImage'])){
            $this->flash->addMessage('error', '¡No has subido ninguna imágen!');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.new'));
        }
        if(empty($files['fileImage']->file) && empty($inputUrl)){
            $this->flash->addMessage('error', '¡No has subido ninguna imágen!');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.new'));
        }
        $smilieExist = Smilie::where('code', $inputCode)->first();
        if($smilieExist){
            $this->session->addWithKey('errors', 'inputCode', "El código \"{$inputCode}\" ya existe. Intenta con otro.");
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.new'));
        }
        if(empty($files['fileImage']->file)){ // Si no subió imagen
            $data = @get_headers($inputUrl, true);
            if($data === false || $data[0] !== 'HTTP/1.1 200 OK'){
                $this->session->addWithKey('errors', 'inputUrl', "No se pudo obtener la imagen con esta URL.");
                return $this->withRedirect($response, $this->router->pathFor('admin.smilie.new'));
            }
            if($data['Content-Length'] > 2e6){
                $this->session->addWithKey('errors', 'inputUrl', "Esta imagen pesa más de dos megabytes (2 MB). Por favor, intenta con otra de menor tamaño.");
                return $this->withRedirect($response, $this->router->pathFor('admin.smilie.new'));
            }
            $smilie = new Smilie();
            $smilie->code = $inputCode;
            $smilie->url = $inputUrl;
            $smilie->save();

            $this->flash->addMessage('success', '¡Se ha agregado el nuevo smilie con éxito!');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie'));
        }

        $smiliePath = __DIR__.'/../../../../public/smilies';
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
            $smilie = new Smilie();
            $smilie->code = $inputCode;
            $smilie->url = $data['fullName'];
            $smilie->local = 1;
            $smilie->save();
            /* Creamos el caché */
            ClientCache::createCache($this->redis);
        }catch(\Exception $e){
            $this->flash->addMessage('error', 'Hubo un error: ' . $e->getMessage());
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.new'));
        }finally{
            $this->flash->addMessage('success', '¡Se ha agregado el nuevo smilie con éxito!');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie'));
        }
    }

    public function putUpdate(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'inputCode' => v::noWhitespace()->notEmpty()->alnum()->length(1, 10),
            'inputUrl' => v::optional(v::noWhitespace()->imageUrl()),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);

        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.update', ['id' => $args['id']]));
        }
        $files = $request->getUploadedFiles();
        $inputCode = $request->getParam('inputCode');
        $inputUrl = $request->getParam('inputUrl');
        $inputToken = $request->getParam('raoToken');
        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.update', ['id' => $args['id']]));
        }
        if(empty($files['fileImage'])){
            $this->flash->addMessage('error', '¡No has subido ninguna imágen!');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.update', ['id' => $args['id']]));
        }
        $currentSmilie = Smilie::find($args['id']);
        if(!$currentSmilie){
            $this->flash->addMessage('error', '¡Éste smilie no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.update', ['id' => $args['id']]));
        }
        $smilieExist = Smilie::where('code', $inputCode)->first();
        if($smilieExist && $smilieExist->id !== $currentSmilie->id){
            $this->session->addWithKey('errors', 'inputCode', "El código \"{$inputCode}\" ya existe. Intenta con otro.");
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.update', ['id' => $args['id']]));
        }
        $smilie = [
            'url' => $currentSmilie->url,
            'local' => $currentSmilie->local,
            'changed' => false
        ];
        $smiliePath = __DIR__.'/../../../../public/smilies';

        if(empty($files['fileImage']->file) && !empty($inputUrl)){ // Si no subió imagen pero si cambió la url
            $data = @get_headers($inputUrl, true);
            if($data === false || !in_array($data[0], ['HTTP/1.0 200 OK', 'HTTP/1.1 200 OK'])){
                $this->session->addWithKey('errors', 'inputUrl', "No se pudo obtener la imagen con esta URL.");
                return $this->withRedirect($response, $this->router->pathFor('admin.smilie.update', ['id' => $args['id']]));
            }
            if($data['Content-Length'] > 2e6){
                $this->session->addWithKey('errors', 'inputUrl', "Esta imagen pesa más de dos megabytes (2 MB). Por favor, intenta con otra de menor tamaño.");
                return $this->withRedirect($response, $this->router->pathFor('admin.smilie.update', ['id' => $args['id']]));
            }
            $currentSmilie->url = $inputUrl;
            $currentSmilie->local = 0;
            $smilie['changed'] = true;
        }else if(!empty($files['fileImage']->file)){
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
                $currentSmilie->url = $data['fullName'];
                $currentSmilie->local = 1;
                $smilie['changed'] = true;
            }catch(\Exception $e){
                $this->flash->addMessage('error', 'Hubo un error: ' . $e->getMessage());
                return $this->withRedirect($response, $this->router->pathFor('admin.smilie.update', ['id' => $args['id']]));
            }
        }
        if($smilie['changed'] && $smilie['local']){ // Eliminamos la antigua imagen.
            if(file_exists($smiliePath.'/'.$smilie['url']))
                unlink($smiliePath.'/'.$smilie['url']);
        }
        $currentSmilie->code = $inputCode;
        $currentSmilie->save();
        /* Creamos el caché */
        ClientCache::createCache($this->redis);
        $this->flash->addMessage('success', '¡Se ha modificado el smilie con éxito!');
        return $this->withRedirect($response, $this->router->pathFor('admin.smilie'));
    }

    public function deleteSmilie(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed() || $validationGet->failed()){
            if($request->isXhr()){
                return $this->showJSONResponse($response, ['error' =>  $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.update', ['id' => $args['id']]));
        }

        $inputToken = $request->getParam('raoToken');
        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.smilie.new'));
        }

        $currentSmilie = Smilie::find($args['id']);
        if(!$currentSmilie){
            return $this->showJSONResponse($response, ['error' => 'Éste Smilie no existe.']);
        }
        $smilePath = __DIR__.'/../../../../public/smilies/';
        if($currentSmilie->local && file_exists($smilePath.$currentSmilie->url)){
            unlink($smilePath.$currentSmilie->url);
        }
        $currentSmilie->delete();
        /* Guardar caché */
        ClientCache::createCache($this->redis);
        return $this->showJSONResponse($response, ['success' => 'El smilie ha sido borrado con éxito.']);
    }
}