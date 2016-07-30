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
        $chatConfig = require __DIR__.'/../../Config/Chat.php';
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
        $chatConfig = require __DIR__.'/../../Config/Chat.php';
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
            $oldfile = $chatConfig['background'];
            $chatConfig['background'] = $file->getNameWithExtension();
            /* Pubsub nueva imagen */
            $this->redis->publish('admin-update-background', json_encode([
                'background' => $request->getUri()->getBaseUrl().'/assets/img/'. $file->getNameWithExtension(),
            ]));
            /* Remover imagenes */
            if(!empty($oldfile)  && file_exists($fondoPath.'/'.$oldfile)){
                unlink($fondoPath.'/'.$oldfile);
            }
            file_put_contents(__DIR__.'/../../Config/Chat.php', '<?php return ' . var_export($chatConfig, true). ";");
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
        $chatConfig = require __DIR__.'/../../Config/Chat.php';
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
            $oldfile = $chatConfig['side'];
            $chatConfig['side'] = $file->getNameWithExtension();
            /* Pubsub nueva imagen */
            $this->redis->publish('admin-update-side', json_encode([
                'background' => $request->getUri()->getBaseUrl().'/assets/img/'. $file->getNameWithExtension(),
            ]));
            /* Remover imagenes */
            if(!empty($oldfile) && file_exists($fondoPath.'/'.$oldfile)){
                unlink($fondoPath.'/'.$oldfile);
            }
            file_put_contents(__DIR__.'/../../Config/Chat.php', '<?php return ' . var_export($chatConfig, true). ";");
            $resp['error'] = false;
            $resp['image'] = $request->getUri()->getBaseUrl().'/assets/img/'. $file->getNameWithExtension();
        }catch(\Exception $e){
            $resp['error'] = true;
            $resp['message'] = 'Hubo un error al actualizar tu imagen, verifica si la imagen es válida';
        }finally{
            $this->showJSONResponse($response, $resp);
        }
    }
    
}