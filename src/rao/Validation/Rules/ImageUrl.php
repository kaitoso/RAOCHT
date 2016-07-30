<?php
namespace App\Validation\Rules;

use Respect\Validation\Rules\AbstractRule;

class ImageUrl extends AbstractRule
{

    public function validate($input)
    {
        return preg_match('/^\b(https?:\/\/\S+(?:png|jpe?g|gif)\S*)\b$/', $input) === 1;
    }
}