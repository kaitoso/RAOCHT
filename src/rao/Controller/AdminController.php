<?php
namespace App\Controller;


use App\Model\User;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class AdminController extends BaseController
{

    public function index(Request $request, Response $response)
    {
       return $this->view->render($response, 'admin/main.twig');
    }

}