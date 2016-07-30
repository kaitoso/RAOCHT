<?php
namespace App\Validation\Exceptions;
use Respect\Validation\Exceptions\ValidationException;

class ImageUrlException extends ValidationException
{

    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => 'Esta no es una imágen válida.',
        ]
    ];
}