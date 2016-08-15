<?php
namespace App\Controller;

use App\Model\ProfileComment;
use App\Model\User;
use App\Model\UserAchievements;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class PerfilController extends BaseController
{

    public function getIndex(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'user' => v::optional(v::noWhitespace()->notEmpty()->alnum('_-')->length(4, 30))
        ]);
        if($validationGet->failed()){
            $this->flash->addMessage('error', 'La validación para el usuario es incorrecta.');
            return $this->withRedirect($response, $this->router->pathFor('main.error'));
        }
        $inputUser = $request->getAttribute('user');
        if(empty($inputUser)){
            $user = User::find($this->session->get('user_id'));
        }else{
            $user = User::where('user', $inputUser)->first();
        }
        if(!$user){
            $this->view->render($response, '404.twig');
            return $response->withStatus(404);
        }
        $who = User::find($this->session->get('user_id'));
        $whoRank = $who->getRank;
        $perm = array_flip(json_decode($whoRank->permissions));
        $delete = !empty($perm['user']) || $user->id === $this->session->get('user_id') ? true : false;
        $chatConfig = json_decode(file_get_contents(__DIR__.'/../Config/Chat.json'));
        return $this->view->render($response, 'perfil.twig', [
            'who' => $who,
            'whoRank' => $whoRank,
            'whoPerm' => (object) $perm,
            'user' => $user,
            'delete' => $delete,
            'config' => $chatConfig
        ]);
    }

    public function getUserInfo(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'user' => v::noWhitespace()->notEmpty()->alnum('_-')->length(4, 30)
        ]);
        if($validationGet->failed()){
            return $response->withJson(['error' => $this->session->get('errors')]);
        }
        $inputUser = $request->getAttribute('user');
        $user = User::where('user', $inputUser)->first();
        if(!$user){
            return $this->showJSONResponse($response, ['error' => 'Éste usuario no éxiste.']);
        }
        $logros = UserAchievements::select('ach.name', 'ach.description', 'ach.image', 'user_achievements.created_at')
            ->join('achievements as ach', 'user_achievements.achievement_id', '=', 'ach.id')
            ->where('user_achievements.user_id', $user->id)
            ->take(4)
            ->orderBy('user_achievements.id', 'desc')
            ->get();
        return $this->showJSONResponse($response, [
            'user' => $user->getProfile->toArray(),
            'logros' => $logros->toArray(),
            'comments' => $this->getComments($user->id)
        ]);
    }

    public function getUser(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'user' => v::noWhitespace()->notEmpty()->alnum('_-')->length(3, 30)
        ]);
        if($validationGet->failed()){
            return $response->withJson(['error' => $this->session->get('errors')]);
        }
        $inputUser = $request->getAttribute('user');
        $userLike = User::select('users.id', 'users.user', 'users.image', 'ranks.name')
            ->leftJoin('ranks', 'ranks.id', '=', 'users.rank')
            ->where('user', 'LIKE', "%{$inputUser}%")
            ->take(4)
            ->get();
        return $this->showJSONResponse($response, $userLike->toArray());
    }

    public function getLogrosJSON(Request $request, Response $response, $args)
    {
        $validationGet = $this->validator->validateArgs($request, [
            'limit' => v::optional(v::notEmpty()->intVal()->positive()),
            'offset' => v::optional(v::notEmpty()->intVal()->positive())
        ]);
        if($validationGet->failed()){
            return $response->withJson(['error' => $this->session->get('errors')]);
        }

        $limit = $request->getAttribute('limit') ?: 4;
        $offset = $request->getAttribute('offset') ?: 0;

        $logros = UserAchievements::select('ach.name', 'ach.description', 'ach.image', 'user_achievements.created_at')
            ->join('achievements as ach', 'user_achievements.achievement_id', '=', 'ach.id')
            ->where('user_achievements.user_id', $this->session->get('user_id'))
            ->take($limit)
            ->offset($offset)
            ->orderBy('user_achievements.id', 'desc')
            ->get();
        return $this->showJSONResponse($response, $logros->toArray());
    }

    public function postComment(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'message' => v::stringType()->notEmpty()->length(1, 1000),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        $validationGet = $this->validator->validateArgs($request, [
            'user' => v::noWhitespace()->notEmpty()->alnum('_-')->length(4, 30)
        ]);
        if($validation->failed() || $validationGet->failed()){
            return $response->withJson(['error' => $this->session->get('errors')]);
        }

        $inputUser = $request->getAttribute('user');
        $inputMessage = $request->getParam('message');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            return $this->showJSONResponse($response, ['error' => 'Este token no existe.']);
        }
        $user = User::where('user', $inputUser)->first();
        if(!$user){
            return $this->showJSONResponse($response, ['error' => 'Éste usuario no éxiste.']);
        }
        $prof = new ProfileComment();
        $prof->user_id = $user->id;
        $prof->who_id = $this->session->get('user_id');
        $prof->message = $inputMessage;
        $prof->save();
        return $this->showJSONResponse($response, ['success' => '¡Se ha agregado el comentario!']);
    }

    public function deleteComment(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validate($request, [
            'id' => v::notEmpty()->intVal()->positive(),
            'raoToken' => v::noWhitespace()->notEmpty()
        ]);
        $validationGet = $this->validator->validateArgs($request, [
            'user' => v::noWhitespace()->notEmpty()->alnum('_-')->length(4, 30)
        ]);
        if($validation->failed() || $validationGet->failed()){
            return $response->withJson(['error' => $this->session->get('errors')]);
        }
        if($validationGet->failed()){
            return $response->withJson(['error' => $this->session->get('errors')]);
        }

        $inputId = $request->getParam('id');
        $inputUser = $request->getAttribute('user');
        $inputToken = $request->getParam('raoToken');

        if($this->session->get('token') !== $inputToken){
            return $this->showJSONResponse($response, ['error' => 'Este token no existe.']);
        }

        $user = User::where('user', $inputUser)->first();
        if(!$user){
            return $this->showJSONResponse($response, ['error' => 'Éste usuario no éxiste.']);
        }
        $userComment = ProfileComment::where([
            ['id', $inputId],
            ['user_id', $user->id]
        ])->first();
        if(!$userComment){
            return $this->showJSONResponse($response, ['error' => 'Éste comentario no éxiste.']);
        }
        $currentUser = User::find($this->session->get('user_id'));
        $powers = json_decode($currentUser->getRank->permissions);
        $powers = array_flip($powers);

        if($userComment->user_id !== $this->session->get('user_id') && empty($powers['user'])){
            return $this->showJSONResponse($response, ['error' => 'No tienes los suficientes permisos para eliminar este comentario.']);
        }
        $userComment->delete();
        return $this->showJSONResponse($response, ['success' => 'Se ha eliminado este comentario con éxito']);
    }


    private function getComments($user_id)
    {
        return ProfileComment::select('profile_comments.id', 'who.user', 'who.chatColor',
            'who.chatText', 'rank.name as rank', 'who.image', 'profile_comments.message', 'profile_comments.created_at')
            ->join('users as who', 'who.id', '=', 'profile_comments.who_id')
            ->join('ranks as rank', 'rank.id', '=', 'who.rank')
            ->where('profile_comments.user_id', $user_id)
            ->take(30)
            ->orderBy('profile_comments.id', 'desc')
            ->get()
            ->toArray();
    }
    
}