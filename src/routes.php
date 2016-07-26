<?php

$app->get('/', 'App\MainController:index')->setName('main.page');
$app->get('/error', 'App\MainController:error')->setName('main.error');
$app->get('/login', 'App\MainController:getLogin')->setName('auth.login');
$app->get('/signup', 'App\MainController:getSignUp')->setName('auth.signup');
$app->get('/logout', 'App\MainController:getLogout')->setName('auth.logout');
$app->get('/fb-login', 'App\Api\Facebook:getFacebookCallback')->setName('auth.facebook');

$app->get('/cuenta', 'App\CuentaController:index')->setName('cuenta.main');
$app->get('/cuenta/fb-link', 'App\Api\Facebook:getFacebookCallbackLink')->setName('cuenta.facebook');
$app->get('/cuenta/fb-unlink', 'App\Api\Facebook:getUnlink')->setName('facebook.logout');
$app->group('/admin', function () {
    $this->get('[/]', 'App\AdminController:index')->setName('admin.main');


    $this->get('/ban[/{name}]', 'App\Admin\BanController:banindex')->setName('admin.ban');
    $this->post('/ban', 'App\Admin\BanController:postBan');

    $this->get('/unban', 'App\Admin\BanController:unbanIndex')->setName('admin.unban');
    $this->delete('/unban/{id}', 'App\Admin\BanController:deleteUnban');

    $this->get('/rank', 'App\Admin\RankController:getIndex')->setName('admin.rank');
    $this->get('/rank/new', 'App\Admin\RankController:getNew')->setName('admin.rank.new');
    $this->get('/rank/{id}', 'App\Admin\RankController:getUpdate')->setName('admin.rank.update');
    $this->post('/rank/new', 'App\Admin\RankController:postNew');
    $this->put('/rank/{id}', 'App\Admin\RankController:putRank');
    $this->delete('/rank/{id}', 'App\Admin\RankController:deleteRank');

    $this->get('/user', 'App\Admin\UserController:getIndex')->setName('admin.user');
    $this->get('/user/new', 'App\Admin\UserController:getNew')->setName('admin.user.new');
    $this->get('/user/{id}', 'App\Admin\UserController:getUpdate')->setName('admin.user.update');


    $this->get('/search/user/{user}', 'App\Admin\SearchController:getUser');
    $this->get('/search/users', 'App\Admin\SearchController:getUsers');
    //$this->get('/search/bans[/{offset}[/{limit}[/{order}]]]', 'App\Admin\SearchController:getBans');
    $this->get('/search/bans', 'App\Admin\SearchController:getBans');
    $this->get('/search/bans/find/{search}[/{offset}[/{limit}[/{order}]]]', 'App\Admin\SearchController:getSearchBans');

    $this->get('/search/ranks', 'App\Admin\SearchController:getRanks');

})->add(new \App\Middleware\AdminMiddleware($app->getContainer()));

$app->get('/search/{query}', 'App\SearchController:getSearch');
$app->get('/articles', 'App\SearchController:getArticles');
$app->get('/title/{query}', 'App\SearchController:getTitle');
$app->get('/chapter/{title}/{id}', 'App\SearchController:getChapter');
$app->get('/article/{query}', 'App\SearchController:getArticle');


$app->post('/login', 'App\MainController:postLogin');
$app->post('/signup', 'App\MainController:postSignUp');
$app->post('/cuenta/image', 'App\CuentaController:postImagen');


$app->put('/cuenta/chatinfo', 'App\CuentaController:putChatInfo');
$app->put('/cuenta/password', 'App\CuentaController:putPassword');
$app->put('/cuenta/email', 'App\CuentaController:putEmail');


$app->post('/search', 'App\SearchController:postFilterSearch');

$app->get('/pass/{id}', function($request, $response, $args){
    $pass = password_hash(base64_encode(
        hash('sha256', $args['id'], true)
    ), PASSWORD_BCRYPT, ['cost' => 12]);
    var_dump($pass);
});