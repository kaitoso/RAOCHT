<?php
namespace App\Database;
use App\Model\Permisos;
use App\Model\RankPermisos;
use Illuminate\Database\Capsule\Manager as Capsule;

class AppSchemas
{
    function up(){

        Capsule::schema()->create('ranks', function($table){
            $table->increments('id');
            $table->string('name');
            $table->boolean('immunity')->unsigned()->default(0);
            $table->string('permissions')->nullable()->default('[]');
            $table->string('chatPermissions')->nullable()->default('[]');
            $table->timestamps();
        });

        Capsule::schema()->create('users', function($table){
            $table->increments('id');
            $table->string('email')->unique();
            $table->string('password', 72);
            $table->string('user', 30)->unique();
            $table->string('image', 100);
            $table->string('chatName', 50);
            $table->string('chatColor', 6)->default('000000');
            $table->string('chatText', 6)->default('000000');
            $table->integer('rank')->unsigned()->default(2);
            $table->boolean('activated')->default(0);
            $table->string('ip', 15);
            $table->bigInteger('facebookId')->unsigned()->nullable();
            $table->string('facebookToken', 510)->nullable();
            $table->bigInteger('twitterId')->unsigned()->nullable();
            $table->string('twitterToken', 510)->nullable();
            $table->string('googleId', 32)->nullable();
            $table->timestamp('lastLogin')->useCurrent();
            $table->timestamps();
            $table->foreign('rank')
                ->references('id')
                ->on('ranks')
                ->onDelete('restrict');
        });

        Capsule::schema()->create('auth_token', function($table){
            $table->increments('id');
            $table->string('selector', 12)->unique();
            $table->string('token', 64);
            $table->integer('user_id')->unsigned();
            $table->timestamp('expires')->useCurrent();
            $table->timestamp('last_used')->useCurrent();
            $table->string('ip', 45);
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Capsule::schema()->create('bans', function($table){
            $table->increments('id');
            $table->integer('user')->unsigned()->unique();
            $table->integer('who')->unsigned();
            $table->dateTime('date_ban')->useCurrent();
            $table->string('reason', 255);
            $table->string('ip', 15);
            $table->timestamps();
            $table->foreign('user')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
            $table->foreign('who')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });

        Capsule::schema()->create('smilies', function($table){
            $table->increments('id');
            $table->string('code', 12)->unique();
            $table->string('url', 255);
            $table->boolean('local')->default(0);
            $table->timestamps();
        });

        Capsule::schema()->create('salas', function($table){
            $table->increments('id');
            $table->string('nombre', 50);
            $table->string('description');
            $table->string('password', 72)->nullable();
            $table->integer('admin_id')->unsigned();
            $table->timestamps();
        });

        Capsule::schema()->create('achievements', function($table){
            $table->increments('id');
            $table->string('name', 50);
            $table->string('description', 100);
            $table->string('image', 255);
            $table->timestamps();
        });

        Capsule::schema()->create('user_achievements', function($table){
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('achievement_id')->unsigned();
            $table->timestamps();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('achievement_id')
                ->references('id')
                ->on('achievements')
                ->onDelete('cascade');
        });

    }

    function createRanks(){
        $rol = new \App\Model\Rank();
        $rol->name = 'Administrador';
        $rol->permissions = '["ban", "unban", "rank", "user", "logro", "chat", "global"]';
        $rol->chatPermissions = '["images","videos","audio"]';
        $rol->save();
        $rol = new \App\Model\Rank();
        $rol->name = 'Nuevo';
        $rol->save();
    }

    function createUsers(){
        $image = 'Sistema'. uniqid();
        Avatar::generateAvatar(
            __DIR__. '/../../../public/avatar/preduser.png',
            $image
        );
        $admin = new \App\Model\User();
        $admin->email = 'contacto@asner.xyz';
        $admin->password = password_hash(base64_encode(
            hash('sha256', 'MGUwZThjMTgxODNlZWE2NWI3NT', true)
        ), PASSWORD_BCRYPT, ['cost' => 10]);
        $admin->user = 'Sistema';
        $admin->rank = 1;
        $admin->chatName = 'Sistema';
        $admin->image = $image;
        $admin->ip = '127.0.0.1';
        $admin->lastLogin = date('Y-m-d H:i:s');
        $admin->save();

        $image = 'Asner'. uniqid();
        Avatar::generateAvatar(
            __DIR__. '/../../../public/avatar/preduser.png',
            $image
        );
        $prueba = new \App\Model\User();
        $prueba->email = 'jose.gaytan@outlook.com';
        $prueba->password = password_hash(base64_encode(
            hash('sha256', '12345678', true)
        ), PASSWORD_BCRYPT, ['cost' => 10]);
        $prueba->user = 'Asner';
        $prueba->rank = 1;
        $prueba->chatName = 'Asner';
        $prueba->image = $image;
        $prueba->ip = '127.0.0.1';
        $prueba->lastLogin = date('Y-m-d H:i:s');
        $prueba->save();
    }

    function seed(){
        $this->createRanks();
        $this->createUsers();
    }

    function down(){
        Capsule::schema()->dropIfExists('smilies');
        Capsule::schema()->dropIfExists('user_achievements');
        Capsule::schema()->dropIfExists('achievements');
        Capsule::schema()->dropIfExists('salas');
        Capsule::schema()->dropIfExists('bans');
        Capsule::schema()->dropIfExists('permissions');
        Capsule::schema()->dropIfExists('auth_token');
        Capsule::schema()->dropIfExists('users');
        Capsule::schema()->dropIfExists('ranks');
    }
}