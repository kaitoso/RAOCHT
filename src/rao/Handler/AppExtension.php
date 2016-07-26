<?php
namespace App\Handler;

use App\Security\Token;
/**
 *
 */
class AppExtension extends \Twig_Extension
{
    public function getName()
    {
        return 'rao';
    }

    public function getGlobals()
    {
        return array(
            'session' => SessionHandler::getInstance(),
        );
    }

    public function getFilters()
    {
        return array(
            'values' => new \Twig_Filter_Method($this, 'values'),
        );
    }

    public function getFunctions(){
        return array(
            new \Twig_SimpleFunction('formToken', array($this, 'formToken'))
        );
    }

    public function values($array)
    {
        if (!isset($array)) {
            return null;
        }

        return array_values((array) $array);
    }

    public function formToken($lock_to = null)
    {
        $session = SessionHandler::getInstance();
        if(empty($session->get('token'))){
            $session->set('token', bin2hex(Token::generateRandom(32)));
        }
        if(empty($session->get('token_key'))){
            $session->set('token_key', Token::generateRandom(32));
        }
        if (empty($lock_to)) {
            return $session->get('token');
        }
        return hash_hmac('sha256', $lock_to, $session->get('token_key'));
    }
}
