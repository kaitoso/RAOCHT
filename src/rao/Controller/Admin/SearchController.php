<?php
namespace App\Controller\Admin;


use App\Controller\BaseController;
use App\Model\Ban;
use App\Model\Rank;
use App\Model\User;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class SearchController extends BaseController
{
    public function getUser(Request $request, Response $response, $args)
    {
        $inputUser = $args['user'];
        $userLike = User::select('users.user', 'users.image', 'ranks.name')
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
            ->where('users.user', 'like', "{$search}%")
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
}