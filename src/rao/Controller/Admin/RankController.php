<?php
namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Handler\ClientCache;
use App\Model\Rank;
use App\Model\User;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class RankController extends BaseController
{

    public function getIndex(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'admin/rank.twig');
    }

    public function getNew(Request $request, Response $response, $args)
    {
        $permisos = require __DIR__.'/../../Config/RankPermissions.php';
        $chatPermisos = require __DIR__.'/../../Config/ClientPermissions.php';
        return $this->view->render($response, 'admin/rank-new.twig', [
            'permisos' => $permisos,
            'chatPermisos' => $chatPermisos
        ]);
    }

    public function getUpdate(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validateArgs($args, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        if($validation->failed()){
            $this->flash->addMessage('error', 'No ingresaste una identificación.');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank'));
        }
        if(in_array($args['id'], [1, 2])) {
            $this->flash->addMessage('error', 'Este rango no se puede modificar. Pertenece a los rangos por default.');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank'));
        }
        $rank = Rank::find($args['id']);
        if(!$rank->exists){
            $this->flash->addMessage('error', '¡Este rango no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank'));
        }
        $permisos = require __DIR__.'/../../Config/RankPermissions.php';
        $chatPermisos = require __DIR__.'/../../Config/ClientPermissions.php';
        return $this->view->render($response, 'admin/rank-update.twig', [
            'permisos' => $permisos,
            'chatPermisos'=> $chatPermisos,
            'rank' => $rank,
            'rankPerm' => json_decode($rank->permissions),
            'rankChat' => json_decode($rank->chatPermissions)
        ]);
    }

    public function postNew(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'inputName' => v::notEmpty()->alnum()->length(4, 50),
            'inputImmunity' => v::boolVal(),
            'inputPermission' => v::arrayVal()->each(v::boolVal()),
            'inputChatPerm' => v::arrayVal()->each(v::boolVal()),
        ]);
        if($validation->failed()){
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.new'));
        }
        $inputName = $request->getParam('inputName');
        $inputInmu = $request->getParam('inputImmunity') ?: false;
        $inputPerm = $request->getParam('inputPermission') ?: array();
        $inputChat = $request->getParam('inputChatPerm') ?: array();
        $rank = Rank::where('name', $inputName)->first();
        if($rank){
            $this->session->addWithKey('errors', 'inputName', '¡Este rango ya existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.new'));
        }
        /* Creamos el nuevo rango */
        $newRank = new Rank();
        $newRank->name = $inputName;
        if($inputInmu === 'on'){
            $newRank->immunity = true;
        }
        $permisos = require __DIR__.'/../../Config/RankPermissions.php';
        $chatPermisos = require __DIR__.'/../../Config/ClientPermissions.php';
        $chatInters = array_intersect_key($permisos, $inputPerm);
        $clientInter = array_intersect_key($chatPermisos, $inputChat);
        $newRank->permissions = json_encode(array_keys($chatInters));
        $newRank->chatPermissions = json_encode(array_keys($clientInter));
        $newRank->save();
        /* Actualizar el caché */
        ClientCache::createCache($this->redis);
        /* Publicar el nuevo rango */

        $this->flash->addMessage('success', '¡Se ha creado el nuevo rango con éxito!');
        return $this->withRedirect($response, $this->router->pathFor('admin.rank'));
    }

    public function putRank(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($args, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'inputName' => v::notEmpty()->alnum()->length(4, 50),
            'inputImmunity' => v::boolVal(),
            'inputPermission' => v::arrayVal()->each(v::boolVal()),
            'inputChatPerm' => v::arrayVal()->each(v::boolVal()),
        ]);
        if($validation->failed() || $validationGet->failed()){
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
        }
        $inputName = $request->getParam('inputName');
        $inputInmu = $request->getParam('inputImmunity') ?: false;
        $inputPerm = $request->getParam('inputPermission') ?: array();
        $inputChat = $request->getParam('inputChatPerm') ?: array();
        $token = $request->getParam('raoToken');
        if($validation->failed()){
            return $this->showJSONResponse($response, ['error' => $this->session->get('errors')]);
        }
        if($this->session->get('token') !== $token){
            return $this->showJSONResponse($response, ['error' => 'Éste token es inválido.']);
        }
        $rankid = $args['id'];
        $rank = Rank::find($rankid);
        if(!$rank) {
            $this->flash->addMessage('success', 'Este rango no existe.');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
        }
        if(in_array($rank->id, [1, 2])) {
            $this->flash->addMessage('success', 'Este rango no se puede eliminar. Pertenece a los rangos por default.');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
        }
        $rankName = Rank::where('name', $inputName)->first();
        if($rankName && $rankName->id !== $rank->id){
            $this->session->addWithKey('errors', 'inputName', '¡El nombre de "' . $inputName .  '" ya esta en uso en otro rango!');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
        }
        $rank->name = $inputName;
        if($inputInmu === 'on'){
            $rank->immunity = true;
        }
        $permisos = require __DIR__.'/../../Config/RankPermissions.php';
        $chatPermisos = require __DIR__.'/../../Config/ClientPermissions.php';
        $chatInters = array_intersect_key($permisos, $inputPerm);
        $clientInter = array_intersect_key($chatPermisos, $inputChat);
        $rank->permissions = json_encode(array_keys($chatInters));
        $rank->chatPermissions = json_encode(array_keys($clientInter));
        $rank->save();
        /* Actualizar el caché */
        ClientCache::createCache($this->redis);
        /* Publicar la modificación del rango */

        $this->flash->addMessage('success', '¡Se ha modificado el rango con éxito!');
        return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
    }

    public function deleteRank(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validateArgs($args, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        $token = $request->getParam('token');
        if($validation->failed()){
            return $this->showJSONResponse($response, ['error' => $this->session->get('errors')]);
        }
        if($this->session->get('token') !== $token){
            return $this->showJSONResponse($response, ['error' => 'Éste token es inválido.']);
        }
        $rankid = $args['id'];
        $rank = Rank::find($rankid);
        if(!$rank) {
            return $this->showJSONResponse($response, ['error' => 'Este rango no existe.']);
        }
        if(in_array($rank->id, [1, 2])) {
            return $this->showJSONResponse($response, ['error' => 'Este rango no se puede eliminar. Pertenece a los rangos por default.']);
        }
        /* Cambiar a todos los usuarios al rango de visitante */
        User::where('rank', $rank->id)
            ->update(['rank' => 2]);
        /* Borrar rango */
        $rank->delete();
        /* Actualizar el caché */
        ClientCache::createCache($this->redis);
        /* Publicar la modificación del rango */

        return $this->showJSONResponse($response, ['success' => 'Se ha eliminado el rango correctamente.']);
    }

}