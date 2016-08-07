<?php
namespace App\Model;

use \Illuminate\Database\Eloquent\Model;
class ProfileComment extends Model
{
    protected $table = 'profile_comments';

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