<?php
namespace App\Model;

use \Illuminate\Database\Eloquent\Model;
/**
*
*/
class AuthToken extends Model
{
    protected $table = 'auth_token';

    protected $guarded = array('id');

    public $timestamps = false;
    
    public function usuario(){
        return $this->belongsTo('App\Model\User', 'user_id', 'id');
    }
}

