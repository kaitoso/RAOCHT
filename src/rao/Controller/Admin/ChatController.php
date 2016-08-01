<?php
namespace App\Controller\Admin;


use App\Controller\BaseController;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class ChatController extends BaseController
{

    public function getIndex(Request $request, Response $response, $args)
    {
        $chatConfig = json_decode(file_get_contents(__DIR__.'/../../Config/Chat.json'));
        return $this->view->render($response, 'admin/chat.twig', ['chat' => $chatConfig]);
    }

    public function postBackground(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            return $response->withJson([
                'error' => 'El token está vacio. Intenta actualizar la página.'
            ]);
        }
        $inputToken = $request->getParam('raoToken');
        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.chat'));
        }
        $files = $request->getUploadedFiles();
        if(empty($files['fileImage']) || empty($files['fileImage']->file)){
            return $this->showJSONResponse($response,[
                'error' => true,
                'message' => 'No has subido ninguna imagen.'
            ]);
        }
        $chatConfig = json_decode(file_get_contents(__DIR__.'/../../Config/Chat.json'));
        $fondoPath = __DIR__.'/../../../../public/assets/img';
        $storage = new \Upload\Storage\FileSystem($fondoPath);
        $file = new \Upload\File('fileImage', $storage);
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
        $resp = array();
        try{
            $file->upload();
            $oldfile = $chatConfig->background;
            $chatConfig->background = $file->getNameWithExtension();
            /* Pubsub nueva imagen */
            $imagePath = $request->getUri()->getBaseUrl().'/assets/img/';
            $this->redis->publish('admin-update-background', json_encode([
                'background' => $imagePath. $chatConfig->background,
                'side' => $chatConfig->side !== null ? $imagePath.$chatConfig->side : null
            ]));
            $this->logger->info('Settings background: '. $imagePath. $chatConfig->background);
            /* Remover imagenes */
            if(!empty($oldfile) && file_exists($fondoPath.'/'.$oldfile)){
                unlink($fondoPath.'/'.$oldfile);
            }
            file_put_contents(__DIR__.'/../../Config/Chat.json', json_encode($chatConfig));
            $resp['error'] = false;
            $resp['image'] = $request->getUri()->getBaseUrl().'/assets/img/'. $file->getNameWithExtension();
        }catch(\Exception $e){
            $resp['error'] = true;
            $resp['message'] = 'Hubo un error al actualizar tu imagen, verifica si la imagen es válida';
        }finally{
            $this->showJSONResponse($response, $resp);
        }
    }

    public function postSide(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            return $response->withJson([
                'error' => 'El token está vacio. Intenta actualizar la página.'
            ]);
        }
        $inputToken = $request->getParam('raoToken');
        if($this->session->get('token') !== $inputToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.chat'));
        }
        $files = $request->getUploadedFiles();
        if(empty($files['fileImage']) || empty($files['fileImage']->file)){
            return $this->showJSONResponse($response,[
                'error' => true,
                'message' => 'No has subido ninguna imagen.'
            ]);
        }
        $chatConfig = json_decode(file_get_contents(__DIR__.'/../../Config/Chat.json'));
        $fondoPath = __DIR__.'/../../../../public/assets/img';
        $storage = new \Upload\Storage\FileSystem($fondoPath);
        $file = new \Upload\File('fileImage', $storage);
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
        $resp = array();
        try{
            $file->upload();
            $oldfile = $chatConfig->side;
            $chatConfig->side = $file->getNameWithExtension();
            file_put_contents(__DIR__.'/../../Config/Chat.json', json_encode($chatConfig));
            /* Pubsub nueva imagen */
            $imagePath = $request->getUri()->getBaseUrl().'/assets/img/';
            $this->redis->publish('admin-update-background', json_encode([
                'background' => $chatConfig->background !== null ? $imagePath.$chatConfig->background : null,
                'side' => $imagePath. $chatConfig->side
            ]));
            $this->logger->info('Settings side: '. $imagePath. $chatConfig->side);
            /* Remover imagenes */
            if(!empty($oldfile) && file_exists($fondoPath.'/'.$oldfile)){
                unlink($fondoPath.'/'.$oldfile);
            }
            $resp['error'] = false;
            $resp['image'] = $request->getUri()->getBaseUrl().'/assets/img/'. $file->getNameWithExtension();
        }catch(\Exception $e){
            $resp['error'] = true;
            $resp['message'] = 'Hubo un error al actualizar tu imagen, verifica si la imagen es válida';
        }finally{
            $this->showJSONResponse($response, $resp);
        }
    }

    public function deleteBackground(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            return $response->withJson([
                'error' => 'El token está vacio. Intenta actualizar la página.'
            ]);
        }

        $chatConfig = json_decode(file_get_contents(__DIR__.'/../../Config/Chat.json'));
        $fondoPath = __DIR__.'/../../../../public/assets/img';
        $oldfile = $chatConfig->background;
        if(empty($oldfile)){
            return $this->showJSONResponse($response, ['error' => 'No hay imagen que borrar.']);
        }
        if(file_exists($fondoPath.'/'.$oldfile)){
            unlink($fondoPath.'/'.$oldfile);
        }
        $chatConfig->background = null;
        /* Pubsub nueva imagen */
        $imagePath = $request->getUri()->getBaseUrl().'/assets/img/';
        $this->redis->publish('admin-update-background', json_encode([
            'background' => $chatConfig->background,
            'side' => $chatConfig->side !== null ? $imagePath.$chatConfig->side : null
        ]));
        file_put_contents(__DIR__.'/../../Config/Chat.json', json_encode($chatConfig));
        return $this->showJSONResponse($response, ['success' => 'Se ha eliminado el fondo correctamente.']);
    }

    public function deleteSide(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        if($validation->failed()){
            return $response->withJson([
                'error' => 'El token está vacio. Intenta actualizar la página.'
            ]);
        }

        $chatConfig = json_decode(file_get_contents(__DIR__.'/../../Config/Chat.json'));
        $fondoPath = __DIR__.'/../../../../public/assets/img';
        $oldfile = $chatConfig->side;
        if(empty($oldfile)){
            return $this->showJSONResponse($response, ['error' => 'No hay imagen que borrar.']);
        }
        if(file_exists($fondoPath.'/'.$oldfile)){
            unlink($fondoPath.'/'.$oldfile);
        }
        $chatConfig->side = null;
        /* Pubsub nueva imagen */
        $imagePath = $request->getUri()->getBaseUrl().'/assets/img/';
        $this->redis->publish('admin-update-background', json_encode([
            'background' => $chatConfig->background !== null ? $imagePath.$chatConfig->background : null,
            'side' => $chatConfig->side
        ]));
        file_put_contents(__DIR__.'/../../Config/Chat.json', json_encode($chatConfig));
        return $this->showJSONResponse($response, ['success' => 'Se ha eliminado el fondo correctamente.']);
    }
}