<?php
namespace App\Model;


use Illuminate\Database\Eloquent\Model;

class UserAchievements extends Model
{

    protected $table = 'user_achievements';

    protected $guarded = array('id');

    public function getCreatedAtAttribute($date)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('U');
    }
    public function getUpdatedAtAttribute($date)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('U');
    }

}