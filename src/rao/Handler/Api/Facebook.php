<?php
namespace App\Handler\Api;

use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;

class Facebook
{
    public static function getFBName($fb, $token){
        try {
            $response = $fb->get('/me', $token);
            $userNode = $response->getGraphUser();
        } catch(FacebookResponseException $e) {
            return false;
        } catch(FacebookSDKException $e) {
            return false;
        }
        return $userNode->getName();
    }
}