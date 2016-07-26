<?php
namespace App\Validation\Exceptions;
use Respect\Validation\Exceptions\ValidationException;

class UserExistException extends ValidationException
{

    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => 'El usuario no existe.',
        ]
    ];
}