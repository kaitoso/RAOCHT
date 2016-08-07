<?php
namespace App\Model;

use \Illuminate\Database\Eloquent\Model;
/**
*
*/
class UserProfile extends Model
{
    protected $table = 'user_profiles';

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    public function getUser(){
        return $this->hasOne('App\Model\User', 'id', 'user_id');
    }
}
