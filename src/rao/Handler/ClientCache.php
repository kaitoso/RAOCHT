<?php
/**
 * Created by PhpStorm.
 * User: joseg
 * Date: 26/07/2016
 * Time: 06:44 PM
 */

namespace App\Handler;


use App\Model\Rank;
use App\Model\Smilie;

class ClientCache
{
    public static function createCache($redis)
    {
        $json = array();
        $json['ranks'] = Rank::select('id', 'name', 'chatPermissions')->get();
        $json['smilies'] = Smilie::select('id', 'code', 'url', 'local')->get()->toArray();

        /* Almacenamos el archivo */
        file_put_contents(__DIR__ . '/../../../public/cache/client.json', json_encode($json));

        /* Enviamos la publicación para actualizar los clientes */
        $redis->publish('update-client', json_encode([
            'client' => true
        ]));
    }
}