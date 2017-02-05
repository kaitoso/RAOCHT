<?php
namespace App\Controller;


use App\Model\Ban;
use App\Model\User;
use App\Model\UserAchievements;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\OAuth2;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AdminController extends BaseController
{

    public function index(Request $request, Response $response)
    {
       return $this->view->render($response, 'admin/main.twig');
    }

    public function getUsers(Request $request, Response $response, $args)
    {
        $db = $this->db;
        $users = User::select($db::raw('DAY(created_at) as dia'), $db::raw('count(*) as cantidad'))
            ->where([
                [$db::raw('MONTH(created_at)'), '=', $db::raw('MONTH(CURDATE())')],
                [$db::raw('YEAR(created_at)'), '=', $db::raw('YEAR(CURDATE())')]
            ])
            ->groupBy('dia')
            ->get();
        $bans = Ban::where([
                [$db::raw('MONTH(created_at)'), '=', $db::raw('MONTH(CURDATE())')],
                [$db::raw('YEAR(created_at)'), '=', $db::raw('YEAR(CURDATE())')]
        ])->count();
        $userAch = UserAchievements::where([
                [$db::raw('MONTH(created_at)'), '=', $db::raw('MONTH(CURDATE())')],
                [$db::raw('YEAR(created_at)'), '=', $db::raw('YEAR(CURDATE())')]
        ])->count();
        $maxDays = date('t');
        $current =date('j');
        $data = $users->pluck('cantidad','dia');
        for ($i=1; $i <= $maxDays; $i++) {
            if(!empty($data[$i])) continue;
            $data[$i] = 0;
        }
        ksort($data);
        $res = [
            'users' => $data,
            'bans' => $bans,
            'logros' => $userAch,
        ];
        return $this->showJSONResponse($response, $res);
    }

}
