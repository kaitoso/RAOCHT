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

        Capsule::schema()->create('permissions', function($table){
            $table->increments('id');
            $table->string('name', 50);
            $table->string('description', 100);
            $table->string('icon', '50');
            $table->string('url');
            $table->timestamps();
        });

        Capsule::schema()->create('rank_permissions', function($table){
            $table->increments('id');
            $table->integer('rank_id')->unsigned();
            $table->integer('permission_id')->unsigned();
            $table->foreign('rank_id')
                ->references('id')
                ->on('ranks')
                ->onDelete('cascade');
            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');
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
            $table->string('twitterToken', 510)->nullable();
            $table->string('googleToken', 510)->nullable();
            $table->timestamp('lastLogin');
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
            $table->timestamp('expires');
            $table->timestamp('last_used');
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
            $table->timestamp('date_ban');
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

        Capsule::schema()->create('salas', function($table){
            $table->increments('id');
            $table->string('nombre', 50);
            $table->string('description');
            $table->string('password', 72)->nullable();
            $table->integer('admin_id')->unsigned();
            $table->timestamps();
        });

        /* Create bans */
    }

    function createRanks(){
        $rol = new \App\Model\Rank();
        $rol->name = 'Administrador';
        $rol->permissions = '["ban", "unban", "rank", "user", "logro", "chat"]';
        $rol->chatPermissions = '["images","videos","audio"]';
        $rol->save();
        $rol = new \App\Model\Rank();
        $rol->name = 'Nuevo';
        $rol->save();
    }

    function createUsers(){
        $admin = new \App\Model\User();
        $admin->email = 'contacto@asner.xyz';
        $admin->password = password_hash(base64_encode(
            hash('sha256', 'MGUwZThjMTgxODNlZWE2NWI3NT', true)
        ), PASSWORD_BCRYPT, ['cost' => 10]);
        $admin->user = 'Sistema';
        $admin->rank = 1;
        $admin->chatName = 'Sistema';
        $admin->image = 'sys.jpg';
        $admin->ip = '127.0.0.1';
        $admin->lastLogin = date('Y-m-d H:i:s');
        $admin->save();

        $prueba = new \App\Model\User();
        $prueba->email = 'jose.gaytan@outlook.com';
        $prueba->password = password_hash(base64_encode(
            hash('sha256', '12345678', true)
        ), PASSWORD_BCRYPT, ['cost' => 10]);
        $prueba->user = 'Asner';
        $prueba->rank = 1;
        $prueba->chatName = 'Asner';
        $prueba->image = 'sys.jpg';
        $prueba->ip = '127.0.0.1';
        $prueba->lastLogin = date('Y-m-d H:i:s');
        $prueba->save();
    }

    function seed(){
        $this->createRanks();
        $this->createUsers();
    }

    function down(){
        Capsule::schema()->dropIfExists('salas');
        Capsule::schema()->dropIfExists('bans');
        Capsule::schema()->dropIfExists('rank_permissions');
        Capsule::schema()->dropIfExists('permissions');
        Capsule::schema()->dropIfExists('auth_token');
        Capsule::schema()->dropIfExists('users');
        Capsule::schema()->dropIfExists('ranks');
    }
}