<?php
namespace App\Controller\Admin;


use App\Controller\BaseController;
use App\Model\Achievement;
use App\Model\Ban;
use App\Model\Rank;
use App\Model\Smilie;
use App\Model\User;
use App\Model\UserAchievements;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class SearchController extends BaseController
{
    public function getUser(Request $request, Response $response, $args)
    {
        $inputUser = $args['user'];
        $userLike = User::select('users.id', 'users.user', 'users.image', 'ranks.name')
            ->leftJoin('ranks', 'ranks.id', '=', 'users.rank')
            ->where('user', 'LIKE', "%{$inputUser}%")
            ->take(4)
            ->get();
        return $this->showJSONResponse($response, $userLike->toArray());
    }

    public function getUsers(Request $request, Response $response, $args)
    {
        $query = $request->getQueryParams();
        $search = !empty($query['search']) ? $query['search'] : '';
        $offset = !empty($query['offset']) ? $query['offset'] : 0;
        $limit  = !empty($query['limit']) ? $query['limit'] : 10;
        $order  = !empty($query['order']) ? $query['order'] : 'asc';
        $users = User::select('users.id', 'users.user', 'ranks.name as rank', 'users.image', 'users.created_at')
            ->join('ranks', 'users.rank', '=', 'ranks.id')
            ->where([
                ['users.id', '>', '2'],
                ['users.user', 'like', "{$search}%"]
            ])
            ->skip($offset)
            ->take($limit)
            ->orderBy('users.id', $order)
            ->get();
        $return = array(
            'rows' => $users->toArray(),
            'total' => User::count()
        );
        return $this->showJSONResponse($response, $return);
    }

    public function getBans(Request $request, Response $response, $args){
        $query = $request->getQueryParams();
        $search = !empty($query['search']) ? $query['search'] : '';
        $offset = !empty($query['offset']) ? $query['offset'] : 0;
        $limit  = !empty($query['limit']) ? $query['limit'] : 10;
        $order  = !empty($query['order']) ? $query['order'] : 'asc';
        $bans = User::select('users.id', 'users.user', 'bans.date_ban', 'bans.reason', 'who.user as who', 'bans.created_at')
            ->join('bans', 'bans.user', '=', 'users.id')
            ->join('users as who', 'bans.who', '=', 'who.id')
            ->where('users.user', 'like', "{$search}%")
            ->skip($offset)
            ->take($limit)
            ->orderBy('users.id', $order)
            ->get();
        $return = array(
            'rows' => $bans->toArray(),
            'total' => Ban::count()
        );
        return $this->showJSONResponse($response, $return);
    }

    public function getRanks(Request $request, Response $response, $args)
    {
        $rangos = Rank::select('id', 'name', 'created_at')->where('id','>', 2)->get();
        $count = $rangos->count();
        return $this->showJSONResponse($response, [
            'ranks' => $rangos->toArray(),
            'count' => $count
        ]);
    }

    public function getSmilies(Request $request, Response $response, $args)
    {
        $query = $request->getQueryParams();
        $search = !empty($query['search']) ? $query['search'] : '';
        $offset = !empty($query['offset']) ? $query['offset'] : 0;
        $limit  = !empty($query['limit']) ? $query['limit'] : 10;
        $order  = !empty($query['order']) ? $query['order'] : 'asc';
        $smilies = Smilie::where('code', 'like', "%{$search}%")
            ->skip($offset)
            ->take($limit)
            ->orderBy('id', $order)
            ->get();
        $return = array(
            'rows' => $smilies->toArray(),
            'total' => Smilie::count()
        );
        return $this->showJSONResponse($response, $return);
    }

    public function getAchievements(Request $request, Response $response, $args)
    {
        $query = $request->getQueryParams();
        $search = !empty($query['search']) ? $query['search'] : '';
        $offset = !empty($query['offset']) ? $query['offset'] : 0;
        $limit  = !empty($query['limit']) ? $query['limit'] : 10;
        $order  = !empty($query['order']) ? $query['order'] : 'asc';
        $logros = Achievement::where('name', 'like', "%{$search}%")
            ->skip($offset)
            ->take($limit)
            ->orderBy('id', $order)
            ->get();
        $return = array(
            'rows' => $logros->toArray(),
            'total' => Achievement::count()
        );
        return $this->showJSONResponse($response, $return);
    }

    public function getAchievementUsers(Request $request, Response $response, $args)
    {
        $validation = $this->validator->validateArgs($request, [
            'id' => v::notEmpty()->notEmpty()->intVal()->positive(),
        ]);
        if($validation->failed()){
            $return = array(
                'rows' => [],
                'total' => 0
            );
            return $this->showJSONResponse($response, $return);
        }
        $query = $request->getQueryParams();
        $search = !empty($query['search']) ? $query['search'] : '';
        $offset = !empty($query['offset']) ? $query['offset'] : 0;
        $limit  = !empty($query['limit']) ? $query['limit'] : 10;
        $order  = !empty($query['order']) ? $query['order'] : 'asc';
        $users = UserAchievements::select('users.id', 'users.user', 'user_achievements.created_at')
            ->join('users', 'user_achievements.user_id', '=', 'users.id')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->where([
                ['achievements.id', $args['id']],
                ['users.user', 'like', "{$search}%"]
            ])
            ->skip($offset)
            ->take($limit)
            ->orderBy('users.id', $order)
            ->get();
        $return = array(
            'rows' => $users->toArray(),
            'total' => UserAchievements::where('achievement_id', $args['id'])->count()
        );
        return $this->showJSONResponse($response, $return);
    }
}