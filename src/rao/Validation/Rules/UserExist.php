<?php
namespace App\Validation\Rules;

use App\Model\User;
use Respect\Validation\Rules\AbstractRule;

class UserExist extends AbstractRule
{

    public function validate($input)
    {
        return User::where('user', $input)->count() === 1;
    }
}