<?php
namespace App\Security;


class Token
{
    const KEY = 'CzSWHei9PB5jNDdlDm2r6uRKxFsvH';

    public static function generateRandom($length)
    {
        if (function_exists('mcrypt_create_iv')) {
            return mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        } else {
            return openssl_random_pseudo_bytes($length);
        }
    }

    public function generate($length)
    {
        return self::generateRandom($length);
    }
}