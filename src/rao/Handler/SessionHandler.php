<?php
namespace App\Handler;

/**
 *
 */
class SessionHandler
{

    private static $isStarted = false;
    private static $instance;

    public static function startSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::$isStarted = true;
            session_name('rao_session');
            session_start();
        }
    }

    /**
     * Singleton de la sessión
     * @return mixed
     */
    public static function getInstance()
    {
        if(self::$instance === null){
            self::$instance = new SessionHandler();
        }
        return self::$instance;
    }

    public function destroySession()
    {
        $_SESSION = array();
        session_regenerate_id(true);
        session_destroy();
    }

    public function get($key, $defaultValue = null)
    {
        return ((!empty($_SESSION[$key])) ? ($_SESSION[$key]) : ($defaultValue));
    }

    public function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public function delete($key)
    {
        unset($_SESSION[$key]);
    }

    public function add($key, $value){
        $_SESSION[$key][] = $value;
    }

    public function addWithKey($key, $arrayKey, $value)
    {
        $_SESSION[$key][$arrayKey][] = $value;
    }

    public function getSessionId()
    {
        return session_id();
    }

    public function __construct()
    {
        self::startSession();
    }
}
