<?php
/**
 * Created by PhpStorm.
 * User: joseg
 * Date: 29/07/2016
 * Time: 06:41 PM
 */

namespace App\Handler;


use App\Model\AuthToken;
use App\Model\User;
use App\Security\Token;
use Slim\Views\Twig;
use Swift_Mailer;
use Swift_Message;

class Email
{
    private $connection;

    /**
     * Email constructor.
     * @param $connection
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function sendActivationEmail(\Twig_Environment $twig, User $user)
    {
        $selector = base64_encode(Token::generateRandom(9));
        $token = Token::generateRandom(33);
        $date = new \DateTime('+1 week');
        /* Guardar token */
        $auth = new AuthToken;
        $auth->selector = $selector;
        $auth->token = hash_hmac('sha256', $token, Token::KEY);
        $auth->user_id = $user->id;
        $auth->activation = 1; // Especificar que este token es de activación
        $auth->expires = $date->format('Y-m-d H:i:s');
        $auth->last_used = date('Y-m-d H:i:s');
        $auth->ip = $user->ip;
        $auth->save();
        $mailer = Swift_Mailer::newInstance($this->connection);
        $message = Swift_Message::newInstance()
            ->setSubject('Activación para el usuario ' . $user->user)
            ->setFrom(array('contacto@asner.xyz' => 'Activaciones - Chat Anime Obsesión'))
            ->setTo($user->email)
            ->setBody(
                $twig->render(
                    'email/activation.twig', [
                        'user' => $user,
                        'token' => $auth->token
                    ]
                ), 'text/html');
        $mailer->send($message);
    }

    public function sendForgotEmail(\Twig_Environment $twig, User $user)
    {
        $selector = base64_encode(Token::generateRandom(9));
        $token = Token::generateRandom(33);
        $date = new \DateTime('+6 hour');
        /* Guardar token */
        $auth = new AuthToken;
        $auth->selector = $selector;
        $auth->token = hash_hmac('sha256', $token, Token::KEY);
        $auth->user_id = $user->id;
        $auth->activation = 1; // Especificar que este token es de activacion
        $auth->expires = $date->format('Y-m-d H:i:s');
        $auth->last_used = date('Y-m-d H:i:s');
        $auth->ip = $user->ip;
        $auth->save();
        $mailer = Swift_Mailer::newInstance($this->connection);
        $message = Swift_Message::newInstance()
            ->setSubject('Recuperación del usuario ' . $user->user)
            ->setFrom(array('contacto@asner.xyz' => 'Activaciones - Chat Anime Obsesión'))
            ->setTo($user->email)
            ->setBody(
                $twig->render(
                    'email/forgot.twig', [
                        'user' => $user,
                        'token' => $auth->token
                    ]
                ), 'text/html');
        $mailer->send($message);
    }


}