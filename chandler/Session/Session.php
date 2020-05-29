<?php declare(strict_types=1);
namespace Chandler\Session;
use Chandler\Patterns\TSimpleSingleton;
use Firebase\JWT\JWT;

/**
 * Session singleton.
 * 
 * @author kurotsun <celestine@vriska.ru>
 */
class Session
{
    /**
     * @var array Associative array of session variables
     */
    private $data;
    /**
     * @var string Web-portal secret key
     */
    private $key;
    
    /**
     * @internal
     */
    private function __construct()
    {
        $this->key = strtr(CHANDLER_ROOT_CONF["security"]["secret"], "-_", "+/");
        
        if(!isset($_COOKIE["CHANDLERSESS"]))
            $this->initSession();
        else
            $this->bootstrapData();
    }
    
    /**
     * Sets CHANDLERSESS cookie to specified token.
     * 
     * @internal
     * @param string $token Token
     * @return void
     */
    private function setSessionCookie(string $token): void
    {
        setcookie(
            "CHANDLERSESS",
            $token,
            time() + 60 * 60 * 24 * ((int) CHANDLER_ROOT_CONF["security"]["sessionDuration"]),
            "/",
            "",
            false,
            true
        );
    }
    
    /**
     * Calculates session token and sets session cookie value to it.
     * This function skips empty keys.
     * 
     * @internal
     * @return void
     */
    private function updateSessionCookie(): void
    {
        $this->data = array_filter($this->data, function($data) {
            return !(is_null($data) && $data !== "");
        });
        
        $this->setSessionCookie(JWT::encode($this->data, ($this->key), "HS512"));
    }
    
    /**
     * Initializes session cookie with empty stub and loads no data.
     * 
     * @internal
     * @return void
     */
    private function initSession(): void
    {
        $token = JWT::encode([], ($this->key), "HS512");
        $this->setSessionCookie($token);
        
        $this->data = [];
    }
    
    /**
     * Reads data from cookie.
     * If cookie is corrupted, session terminates and starts again.
     * 
     * @internal
     * @uses \Chandler\Session\Session::initSession
     * @return void
     */
    private function bootstrapData(): void
    {
        try {
            $this->data = (array) JWT::decode($_COOKIE["CHANDLERSESS"], ($this->key), ["HS512"]);
        } catch(\Exception $ex) {
            $this->initSession();
        }
    }
    
    /**
     * Gets session variable.
     * May also set a variable if default value is present and
     * setting keys to default is permitted.
     * 
     * @api
     * @param string $key Session variable name
     * @param scalar $default Default value
     * @param bool $ser Set variable to default value if no data is present
     * @uses \Chandler\Session\Session::set
     * @return scalar
     */
    function get(string $key, $default = null, bool $set = false)
    {
        return $this->data[sha1($key)] ?? ($set ? $this->set($key, $default) : $default);
    }
    
    /**
     * Sets session variable.
     * 
     * @api
     * @param string $key Session variable name
     * @param scalar $value Value
     * @return scalar Value
     */
    function set(string $key, $value)
    {
        $this->data[sha1($key)] = $value;
        $this->updateSessionCookie();
        
        return $value;
    }
    
    use TSimpleSingleton;
}
