<?php
namespace App\Model;

use \Illuminate\Database\Eloquent\Model;
/**
*
*/
class Ban extends Model
{
    protected $table = 'bans';

    protected $guarded = array('id');

    public function whoBanned()
    {
        return $this->hasOne('App\Model\User', 'id', 'who');
    }
}
