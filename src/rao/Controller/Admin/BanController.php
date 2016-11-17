<?php
namespace App\Controller\Admin;


use App\Controller\BaseController;
use App\Model\Ban;
use App\Model\User;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class BanController extends BaseController
{

    public function banindex(Request $request, Response $response, $args)
    {
        $banUser = !empty($args['name']) ? $args['name'] : null;
        $this->view->render($response, 'admin/ban.twig', ['banUser' => $banUser]);
    }

    public function unbanIndex(Request $request, Response $response, $args)
    {
        $this->view->render($response, 'admin/unban.twig');
    }

    public function postBan(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'inputName' => v::noWhitespace()->notEmpty()->alnum('_-')->length(4, 50),
            'banTime' => v::notEmpty()->intVal()->positive(),
            'inputRazon' => v::notEmpty()->alnum(',;.:-_^*+-/¡!¿?()áéíóúÁÉÍÓÚñÑ')->length(4, 255),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);

        if($validation->failed()){
            if($request->isXhr()){
                return $response->withJson(['error' => $this->session->get('errors')]);
            }
            return $this->withRedirect($response,  $this->router->pathFor('admin.ban'));
        }

        $inputName = $request->getParam('inputName');
        $inputTime = $request->getParam('banTime');
        $inputReason = $request->getParam('inputRazon');
        $raoToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $raoToken){
            $this->flash->addMessage('error', 'Éste token es inválido.');
            return $this->withRedirect($response, $this->router->pathFor('admin.ban'));
        }

        $user = User::where('user', $inputName)->first();
        if(!$user->exists){
            $this->session->addWithKey('errors', 'inputName', '¡Este usuario no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.ban'));
        }
        /* Verificar si el usuario tiene inmunidad */
        $rank = $user->getRank;
        if($rank->immunity){
            $this->flash->addMessage('error', 'Este usuario por su rango de ' . $rank->name .' tiene imunidad a la expulsión.');
            return $this->withRedirect($response, $this->router->pathFor('admin.ban'));
        }
        /* Saber si existe la expulsión */
        $ban = Ban::where('user', $user->id)->first();
        if(!$ban){
            $dateBan = date('Y-m-d H:i:s', time() + ($inputTime * 60));
            $newBan = new Ban();
            $newBan->user = $user->id;
            $newBan->who = $this->session->get('user_id');
            $newBan->reason = $inputReason;
            $newBan->date_ban = $dateBan;
            $newBan->ip = $user->ip;
            $newBan->save();
            $this->logger->info("Baneando al usuario {$user->id} - {$user->user} por " . $date_ban);
            /* Publicar usuario al servidor del chat para kikearlo */
            $this->redis->publish('ban-chat', json_encode([
                'id' => $user->id,
                'who' => $this->session->get('user_id')
            ]));
            $this->flash->addMessage('success', '¡Se ha expulsado al usuario ' . $user->user . ' con éxito!');
        }else{
            $who = $ban->whoBanned;
            $this->flash->addMessage('error', 'Este usuario fue expulsado previamente por '. $who->user);
        }
        return $this->withRedirect($response, $this->router->pathFor('admin.ban'));
    }

    public function deleteUnban(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->intVal()->positive(),
        ]);
        $token = $request->getParam('token');
        if($validation->failed()){
            return $this->showJSONResponse($response, ['error' => $this->session->get('errors')]);
        }
        if($this->session->get('token') !== $token){
            return $this->showJSONResponse($response, ['error' => 'Éste token es inválido.']);
        }
        $userid = $args['id'];
        $ban = Ban::where('user', $userid)->first();
        if(!$ban->exists){
            return $this->showJSONResponse($response, ['error' => 'Esta expulsación no existe.']);
        }
        $ban->delete();
        return $this->showJSONResponse($response, ['success' => 'Se ha admitido correctamente al usuario.']);
    }
}
