<?php
namespace App\Model;

use \Illuminate\Database\Eloquent\Model;
/**
*
*/
class User extends Model
{
    protected $table = 'users';

    protected $guarded = array('id');

    public function getRank(){
        return $this->hasOne('App\Model\Rank', 'id', 'rank');
    }

    public function getBan(){
        return $this->hasOne('App\Model\Ban', 'user', 'id');
    }
}
