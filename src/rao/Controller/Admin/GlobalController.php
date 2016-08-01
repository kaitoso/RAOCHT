<?php
namespace App\Controller\Admin;

use App\Controller\BaseController;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class GlobalController extends BaseController
{
    public function getIndex(Request $request, Response $response, $args)
    {
        $this->view->render($response, 'admin/global.twig');
    }

    public function postGlobal(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'inputMessage' =>  v::notEmpty()->alnum(',;.:-_^*+-/¡!¿?()áéíóúÁÉÍÓÚñÑ')
        ]);

        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response, $this->router->pathFor('admin.global'));
        }

        $inputMessage = $request->getParam('inputMessage');

        $this->redis->publish('admin-global', json_encode([
            'id' => $this->session->getSessionId(),
            'user' => $this->session->get('user'),
            'message' => $inputMessage
        ]));

        $this->flash->addMessage('success', '¡Se ha enviado éste mensaje global al chat!');
        return $this->withRedirect($response, $this->router->pathFor('admin.global'));
    }
}