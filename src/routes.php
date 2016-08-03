<?php

$app->get('/', 'App\MainController:index')->setName('main.page');
$app->get('/error', 'App\MainController:error')->setName('main.error');
$app->get('/login', 'App\MainController:getLogin')->setName('auth.login');
$app->get('/signup', 'App\MainController:getSignUp')->setName('auth.signup');
$app->get('/logout', 'App\MainController:getLogout')->setName('auth.logout');
$app->group('/email', function(){
   $this->get('/subscribe/{token}', 'App\SubscriptionController:getActivation');
    $this->get('/unsubscribe/{token}', 'App\SubscriptionController:getUnsubscribe');
});

$app->get('/facebook/login', 'App\Api\Facebook:getIndex')->setName('auth.facebook');
$app->get('/facebook/callback', 'App\Api\Facebook:getFacebookCallback')->setName('auth.facebook.callback');

$app->get('/twitter/login', 'App\Api\Twitter:getIndex')->setName('auth.twitter');
$app->get('/twitter/callback', 'App\Api\Twitter:getCallback')->setName('auth.twitter.callback');

$app->get('/google/login', 'App\Api\Google:getIndex')->setName('auth.google');
$app->get('/google/callback', 'App\Api\Google:getCallback')->setName('auth.google.callback');

$app->group('/cuenta', function(){
    $this->get('[/]', 'App\CuentaController:index')->setName('cuenta.main');
    $this->get('/logros.json[/{id}]', 'App\CuentaController:getLogros')->setName('cuenta.logros');
    $this->get('/facebook/login', 'App\Api\Facebook:getCuentaLogin')->setName('cuenta.facebook.login');
    $this->get('/facebook/callback', 'App\Api\Facebook:getFacebookCallbackLink')->setName('cuenta.facebook.callback');
    $this->get('/facebook/unlink', 'App\Api\Facebook:getUnlink')->setName('cuenta.facebook.logout');

    $this->get('/twitter/login','App\Api\Twitter:getLink')->setName('cuenta.twitter.login');
    $this->get('/twitter/callback','App\Api\Twitter:getCuentaCallback')->setName('cuenta.twitter.callback');
    $this->get('/twitter/unlink', 'App\Api\Twitter:getUnlink')->setName('cuenta.twitter.logout');

    $this->get('/google/login', 'App\Api\Google:getLink')->setName('cuenta.google.login');
    $this->get('/google/callback', 'App\Api\Google:getLinkCallback')->setName('cuenta.google.callback');
    $this->get('/google/unlink', 'App\Api\Google:getUnlink')->setName('cuenta.google.logout');

    $this->put('/chatinfo', 'App\CuentaController:putChatInfo');
    $this->put('/password', 'App\CuentaController:putPassword');
    $this->put('/email', 'App\CuentaController:putEmail');
});

$app->group('/admin', function () {
    $this->get('[/]', 'App\AdminController:index')->setName('admin.main');
    $this->get('/stats', 'App\AdminController:getUsers')->setName('admin.main.stats');

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
    $this->post('/user/new', 'App\Admin\UserController:postNew');
    $this->post('/user/{id}/image', 'App\Admin\UserController:postImage');
    $this->put('/user/{id}/general', 'App\Admin\UserController:putGeneral');
    $this->put('/user/{id}/chatinfo', 'App\Admin\UserController:putChatInfo');
    $this->put('/user/{id}/password', 'App\Admin\UserController:putPassword');
    $this->put('/user/{id}/email', 'App\Admin\UserController:putEmail');
    $this->delete('/user/{id}/delete', 'App\Admin\UserController:deleteUser');

    $this->get('/smilie', 'App\Admin\SmilieController:getIndex')->setName('admin.smilie');
    $this->get('/smilie/new', 'App\Admin\SmilieController:getNew')->setName('admin.smilie.new');
    $this->get('/smilie/{id}', 'App\Admin\SmilieController:getUpdate')->setName('admin.smilie.update');
    $this->post('/smilie/new', 'App\Admin\SmilieController:postNew');
    $this->put('/smilie/{id}', 'App\Admin\SmilieController:putUpdate');
    $this->delete('/smilie/{id}', 'App\Admin\SmilieController:deleteSmilie');

    $this->get('/logro', 'App\Admin\LogroController:getIndex')->setName('admin.logro');
    $this->get('/logro/new', 'App\Admin\LogroController:getNew')->setName('admin.logro.new');
    $this->get('/logro/{id}', 'App\Admin\LogroController:getUpdate')->setName('admin.logro.update');
    $this->post('/logro/new', 'App\Admin\LogroController:postNew');
    $this->post('/logro/user', 'App\Admin\LogroController:postUser');
    $this->delete('/logro/user', 'App\Admin\LogroController:deleteUser');
    $this->post('/logro/global', 'App\Admin\LogroController:postGlobal');
    $this->put('/logro/{id}', 'App\Admin\LogroController:putUpdate');
    $this->delete('/logro/{id}', 'App\Admin\LogroController:deleteLogro');

    $this->get('/global', 'App\Admin\GlobalController:getIndex')->setName('admin.global');
    $this->post('/global', 'App\Admin\GlobalController:postGlobal');

    $this->get('/chat', 'App\Admin\ChatController:getIndex')->setName('admin.chat');
    $this->post('/chat/background', 'App\Admin\ChatController:postBackground');
    $this->post('/chat/side', 'App\Admin\ChatController:postSide');
    $this->post('/chat/welcome', 'App\Admin\ChatController:postWelcome')->setName('admin.chat.welcome');
    $this->delete('/chat/background','App\Admin\ChatController:deleteBackground');
    $this->delete('/chat/side','App\Admin\ChatController:deleteSide');

    $this->get('/search/user/{user}', 'App\Admin\SearchController:getUser');
    $this->get('/search/users', 'App\Admin\SearchController:getUsers');
    //$this->get('/search/bans[/{offset}[/{limit}[/{order}]]]', 'App\Admin\SearchController:getBans');
    $this->get('/search/bans', 'App\Admin\SearchController:getBans');
    $this->get('/search/smilies', 'App\Admin\SearchController:getSmilies');
    $this->get('/search/ranks', 'App\Admin\SearchController:getRanks');
    $this->get('/search/achievements', 'App\Admin\SearchController:getAchievements');
    $this->get('/search/achievements/{id}', 'App\Admin\SearchController:getAchievementUsers');

})->add(new \App\Middleware\AdminMiddleware($app->getContainer()));

$app->get('/search/{query}', 'App\SearchController:getSearch');
$app->get('/articles', 'App\SearchController:getArticles');
$app->get('/title/{query}', 'App\SearchController:getTitle');
$app->get('/chapter/{title}/{id}', 'App\SearchController:getChapter');
$app->get('/article/{query}', 'App\SearchController:getArticle');


$app->post('/login', 'App\MainController:postLogin');
$app->post('/signup', 'App\MainController:postSignUp');
$app->post('/cuenta/image', 'App\CuentaController:postImagen');

$app->post('/search', 'App\SearchController:postFilterSearch');

$app->get('/pass/{id}', function($request, $response, $args){
    $pass = password_hash(base64_encode(
        hash('sha256', $args['id'], true)
    ), PASSWORD_BCRYPT, ['cost' => 12]);
    var_dump($pass);
});