<?php

namespace App\Controller;

use App\Model\PrivateMessage;
use App\Model\User;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Validator as v;

class PrivadoController extends BaseController
{

    public function getIndex(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'private.twig', ['userid' => $this->session->get('user_id')]);
    }

    public function getMessageUsers(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validateArgs($request, [
            'limit' => v::optional(v::intVal()->positive()->min(5, true)->max(30, true)),
            'offset' => v::optional(v::intVal()->positive())
        ]);
        if($validation->failed()){
            return $this->showJSONResponse($response, ['error' => $this->session->get('errors')]);
        }

        $limit = $request->getAttribute('limit') ?: 10;
        $offset = $request->getAttribute('offset') ?: 0;
        $sessid = $this->session->get('user_id');

        $pvs = PrivateMessage::select( 'private_messages.id as mgsid',
                'users.id', 'users.user', 'users.image', 'users.chatColor', 'users.chatText',
                'to.id as to_id', 'to.user as to_user', 'to.image as to_image', 'to.chatColor as to_color',
                'to.chatText as to_text', 'private_messages.message', 'private_messages.seen',
                'private_messages.send_date'
            )
            ->join('users', 'users.id', '=', 'private_messages.from_id')
            ->join('users as to', 'to.id', '=', 'private_messages.to_id')
        ->whereRaw('
            private_messages.id IN(
            SELECT MAX(id) AS id FROM
                (SELECT id, from_id AS id_with
                FROM private_messages
                WHERE to_id = ?
                UNION ALL
                SELECT id, to_id AS id_with
                FROM private_messages
                WHERE from_id = ?) t
            GROUP BY id_with)
        ', [$sessid, $sessid])
            ->skip($offset)
            ->take($limit)
            ->orderBy('private_messages.id', 'desc')
            ->get();
        return $this->showJSONResponse($response, $pvs->toArray());
    }

    public function getUserMessage(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->intVal()->positive(),
            'limit' => v::optional(v::intVal()->positive()->min(5, true)->max(30, true)),
            'offset' => v::optional(v::intVal()->positive())
        ]);
        if($validation->failed()){
            return $this->showJSONResponse($response, ['error' => $this->session->get('errors')]);
        }
        $userid = $request->getAttribute('id');
        $limit = $request->getAttribute('limit') ?: 30;
        $offset = $request->getAttribute('offset') ?: 0;
        $sessid = $this->session->get('user_id');
        $user = User::find($userid);
        if(!$user){
            return $this->showJSONResponse($response, ['error' => 'Éste usuario no existe.']);
        }
        PrivateMessage::where('to_id', $sessid)
            ->where('from_id', $user->id)
            ->update(['seen' => 1]);
        $pvs = PrivateMessage::select(
            'users.id', 'users.user', 'users.image', 'users.chatColor', 'users.chatText', 'users.rank',
            'private_messages.message', 'private_messages.send_date'
        )
            ->join('users', 'users.id', '=', 'private_messages.from_id')
            ->where(function($query) use ($user, $sessid){
                $query->where('private_messages.from_id', $user->id)
                ->where('private_messages.to_id', $sessid);
            })
            ->orWhere(function($query) use ($user, $sessid){
                $query->where('private_messages.from_id', $sessid)
                    ->where('private_messages.to_id', $user->id);
            })
            ->skip($offset)
            ->take($limit)
            ->orderBy('private_messages.id', 'desc')
            ->get();
        return $this->showJSONResponse($response, $pvs->toArray());
    }



}