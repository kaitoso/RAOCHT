<?php
namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Handler\ClientCache;
use App\Model\Achievement;
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
        $rank = Rank::whereNotIn('id', [1, 2])->get();
        return $this->view->render($response, 'admin/rank-new.twig', [
            'permisos' => $permisos,
            'chatPermisos' => $chatPermisos,
            'selectRank' => $rank
        ]);
    }

    public function getUpdate(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->intVal()->positive(),
        ]);
        if($validation->failed()){
            $this->flash->addMessage('error', 'No ingresaste una identificación.');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank'));
        }
        if(in_array($args['id'], [1])) {
            $this->flash->addMessage('error', 'Este rango no se puede modificar. Pertenece a los rangos por default.');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank'));
        }
        $rank = Rank::find($args['id']);
        if(!$rank->exists){
            $this->flash->addMessage('error', '¡Este rango no existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank'));
        }
        $timeLeft = $rank->nextTime;
        $days = floor($timeLeft / 1000 / 60 / 60 / 24);
        $timeLeft -= $days * 1000 * 60 * 60 * 24;
        $hours = floor($timeLeft / 1000 / 60 / 60);
        $permisos = require __DIR__.'/../../Config/RankPermissions.php';
        $chatPermisos = require __DIR__.'/../../Config/ClientPermissions.php';
        $logro = Achievement::find($rank->nextAchievement);
        $selectRank = Rank::whereNotIn('id', [1, 2])->get();
        return $this->view->render($response, 'admin/rank-update.twig', [
            'chatPermisos'=> $chatPermisos,
            'rank' => $rank,
            'rankPerm' => json_decode($rank->permissions),
            'rankChat' => json_decode($rank->chatPermissions),
            'selectRank' => $selectRank,
            'logro' => $logro,
            'time' => [
                'days' => $days,
                'hours' => $hours
            ]
        ]);
    }

    public function postNew(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'inputName' => v::notEmpty()->alnum()->length(4, 50),
            'inputImmunity' => v::boolVal(),
            'inputPermission' => v::arrayVal()->each(v::boolVal()),
            'inputChatPerm' => v::arrayVal()->each(v::boolVal()),
            'inputNext' => v::optional(v::intVal()->min(0, true)),
            'inputDays' => v::optional(v::intVal()->min(0, true)->max(3700, true)),
            'inputHours' => v::optional(v::intVal()->min(0, true)->max(23, true)),
            'inputMessages' => v::optional(v::intVal()->min(0, true)->max(4294967295, true)),
            'inputLogro' => v::optional(v::intVal()->min(0, true)),
        ]);
        if($validation->failed()){
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.new'));
        }
        $inputName = $request->getParam('inputName');
        $inputInmu = $request->getParam('inputImmunity') ?: false;
        $inputPerm = $request->getParam('inputPermission') ?: array();
        $inputChat = $request->getParam('inputChatPerm') ?: array();
        $inputNext = $request->getParam('inputNext');
        $inputDays = $request->getParam('inputDays');
        $inputHour = $request->getParam('inputHours');
        $inputMess = $request->getParam('inputMessages');
        $inputLogr = $request->getParam('inputLogro');
        $rank = Rank::where('name', $inputName)->first();
        if($rank){
            $this->session->addWithKey('errors', 'inputName', '¡Este rango ya existe!');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.new'));
        }
        if(!empty($inputNext)){
            $rank = Rank::find($inputNext);
            if(!$rank){
                $this->session->addWithKey('errors', 'inputNext', '¡Este rango no existe!');
                return $this->withRedirect($response, $this->router->pathFor('admin.rank.new'));
            }
            if((empty($inputDays) && empty($inputHour)) && empty($inputMess)){
                $this->session->addWithKey('errors', 'inputDays', 'Al siguiente rango se debe llegar por tiempo y/o mensajes. No debe estar vacíos.');
                $this->session->addWithKey('errors', 'inputMessages', 'Al siguiente rango se debe llegar por tiempo y/o mensajes. No debe estar vacíos.');
                return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
            }
            if(!empty($inputLogr)) {
                $logro = Achievement::find($inputLogr);
                if (!$logro) {
                    $this->session->addWithKey('errors', 'inputLogro', '¡Este logro no existe!');
                    return $this->withRedirect($response, $this->router->pathFor('admin.rank.new'));
                }
            }
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
        if(!empty($inputNext)){
            $newRank->nextRank = $inputNext;
            $nextTime = ($inputDays * 24 * 60 * 60 * 1000) + ($inputHour * 60 * 60 * 1000);
            if(!empty($nextTime)){
                $newRank->nextTime = $nextTime;
            }
            if(!empty($inputMess)){
                $newRank->nextMessages = $inputMess;
            }
            if(!empty($inputLogr)){
                $newRank->nextAchievement = $inputLogr;
            }
        }
        $newRank->save();
        /* Actualizar el caché */
        ClientCache::createCache($this->redis);
        /* Publicar el nuevo rango */

        $this->flash->addMessage('success', '¡Se ha creado el nuevo rango con éxito!');
        return $this->withRedirect($response, $this->router->pathFor('admin.rank'));
    }

    public function putRank(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->intVal()->positive(),
        ]);
        $validation = $this->validator->validate($request, [
            'inputName' => v::notEmpty()->alnum()->length(4, 50),
            'inputImmunity' => v::boolVal(),
            'inputPermission' => v::arrayVal()->each(v::boolVal()),
            'inputChatPerm' => v::arrayVal()->each(v::boolVal()),
            'inputNext' => v::optional(v::intVal()->min(0, true)),
            'inputDays' => v::optional(v::intVal()->min(0, true)->max(3700, true)),
            'inputHours' => v::optional(v::intVal()->min(0, true)->max(23, true)),
            'inputMessages' => v::optional(v::intVal()->min(0, true)->max(4294967295, true)),
            'inputLogro' => v::optional(v::intVal()->min(0, true)),
        ]);
        if($validation->failed() || $validationGet->failed()){
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
        }
        $inputName = $request->getParam('inputName');
        $inputInmu = $request->getParam('inputImmunity') ?: false;
        $inputPerm = $request->getParam('inputPermission') ?: array();
        $inputChat = $request->getParam('inputChatPerm') ?: array();
        $inputNext = $request->getParam('inputNext');
        $inputDays = $request->getParam('inputDays');
        $inputHour = $request->getParam('inputHours');
        $inputMess = $request->getParam('inputMessages');
        $inputLogr = $request->getParam('inputLogro');
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
            $this->flash->addMessage('error', 'Este rango no existe.');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
        }
        if(in_array($rank->id, [1])) {
            $this->flash->addMessage('error', 'Este rango no se puede modificar. Pertenece a los rangos por default.');
            return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
        }
        if(!empty($inputNext)){
            if(in_array($inputNext, [1,2])){
                $this->session->addWithKey('errors', 'inputNext', 'Este rango pertenece a los rangos por default. No se puede llegar a estos.');
                return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
            }
            if($inputNext === $rank->id){
                $this->session->addWithKey('errors', 'inputNext', 'El siguiente rango no puede ser igual al actual.');
                return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
            }
            $nextRank = Rank::find($inputNext);
            if(!$nextRank){
                $this->session->addWithKey('errors', 'inputNext', '¡Este rango no existe!');
                return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
            }
            if((empty($inputDays) && empty($inputHour)) && empty($inputMess)){
                $this->session->addWithKey('errors', 'inputDays', 'Al siguiente rango se debe llegar por tiempo y/o mensajes. No debe estar vacíos.');
                $this->session->addWithKey('errors', 'inputMessages', 'Al siguiente rango se debe llegar por tiempo y/o mensajes. No debe estar vacíos.');
                return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
            }
            if(!empty($inputLogr)) {
                $logro = Achievement::find($inputLogr);
                if (!$logro) {
                    $this->session->addWithKey('errors', 'inputLogro', '¡Este logro no existe!');
                    return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
                }
            }
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
        if(!empty($inputNext)){
            $rank->nextRank = $inputNext;
            $nextTime = ($inputDays * 24 * 60 * 60 * 1000) + ($inputHour * 60 * 60 * 1000);
            if(!empty($nextTime)){
                $rank->nextTime = $nextTime;
            }
            if(!empty($inputMess)){
                $rank->nextMessages = $inputMess;
            }
            if(!empty($inputLogr)){
                $rank->nextAchievement = $inputLogr;
            }
        }else{
            $rank->nextRank = null;
            $rank->nextTime = null;
            $rank->nextMessages = null;
            $rank->nextAchievement = null;
        }
        $rank->save();
        /* Actualizar el caché */
        ClientCache::createCache($this->redis);
        /* Publicar la modificación del rango */

        $this->flash->addMessage('success', '¡Se ha modificado el rango con éxito!');
        return $this->withRedirect($response, $this->router->pathFor('admin.rank.update', ['id' => $args['id']]));
    }

    public function deleteRank(Request $request, Response $response, $args)
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