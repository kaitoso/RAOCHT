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

    public function getCreatedAtAttribute($date)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('U');
    }
    public function getUpdatedAtAttribute($date)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('U');
    }

    public function getDateBanAttribute($date)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('U');
    }
}
