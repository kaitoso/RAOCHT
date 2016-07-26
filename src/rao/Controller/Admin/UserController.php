<?php
namespace App\Controller\Admin;


use App\Controller\BaseController;
use App\Model\Rank;
use App\Model\User;
use Respect\Validation\Validator as v;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
class UserController extends BaseController
{

    public function getIndex(Request $request, Response $response, $args)
    {
        return $this->view->render($response, 'admin/user.twig');
    }

    public function getNew(Request $request, Response $response, $args)
    {
        $rangos = Rank::get();
        return $this->view->render($response, 'admin/user-new.twig');
    }
}