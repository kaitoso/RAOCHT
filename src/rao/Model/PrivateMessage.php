<?php
namespace App\Model;

use \Illuminate\Database\Eloquent\Model;
/**
*
*/
class PrivateMessage extends Model
{
    protected $table = 'private_messages';

    protected $guarded = array('id');

    public $timestamps = false;

    public function getFromUser(){
        return $this->belongsTo('App\Model\User', 'from_id', 'id');
    }

    public function getToUser(){
        return $this->belongsTo('App\Model\User', 'to_id', 'id');
    }

    public function getSendDateAttribute($date)
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date)->format('U');
    }
}

