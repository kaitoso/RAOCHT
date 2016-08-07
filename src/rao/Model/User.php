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

    protected $dates = ['created_at', 'updated_at', 'lastLogin'];

    public function getRank(){
        return $this->hasOne('App\Model\Rank', 'id', 'rank');
    }

    public function getBan(){
        return $this->hasOne('App\Model\Ban', 'user', 'id');
    }

    public function getProfile(){
        return $this->hasOne('App\Model\UserProfile', 'user_id', 'id');
    }

    public function getCreatedAtAttribute($date)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('U');
    }
    public function getUpdatedAtAttribute($date)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('U');
    }

    public function getLastLoginAttribute($date)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('U');
    }
}
