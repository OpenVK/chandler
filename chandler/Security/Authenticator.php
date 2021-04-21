<?php declare(strict_types=1);
namespace Chandler\Security;
use Chandler\Session\Session;
use Chandler\Patterns\TSimpleSingleton;
use Chandler\Database\DatabaseConnection;

class Authenticator
{
    private $db;
    private $session;
    
    private function __construct()
    {
        $this->db      = DatabaseConnection::i()->getContext();
        $this->session = Session::i();
    }
    
    private function verifySuRights(string $uId): bool
    {
        
    }
    
    private function makeToken(string $user, string $ip, string $ua): string
    {
        $data  = ["user" => $user, "ip" => $ip, "ua" => $ua];
        $token = $this->db
                      ->table("ChandlerTokens")
                      ->where($data)
                      ->fetch();
        
        if(!$token) {
            $this->db->table("ChandlerTokens")->insert($data);
            $token = $this->db->table("ChandlerTokens")->where($data)->fetch();
        }
        
        return $token->token;
    }
    
    static function verifyHash(string $input, string $hash): bool
    {
        try {
            [$hash, $salt] = explode("$", $hash);
            $userHash      = bin2hex(
                sodium_crypto_pwhash(
                    16,
                    $input,
                    hex2bin($salt),
                    SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                    SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
                    SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
                )
            );
            if(sodium_memcmp($hash, $userHash) !== 0) return false;
        } catch(\SodiumException $ex) {
            return false;
        }
        
        return true;
    }
    
    function getUser(): ?User
    {
        $token = $this->session->get("tok");
        if(!$token) return null;
        
        $token = $this->db
                      ->table("ChandlerTokens")
                      ->where([
                          "token" => $token,
                      ])
                      ->fetch();
        
        if(!$token) return null;
        
        $checksPassed = false;
        if(CHANDLER_ROOT_CONF["security"]["extendedValidation"])
            $checksPassed = $token->ip === CONNECTING_IP && $token->ua === $_SERVER["HTTP_USER_AGENT"];
        else
            $checksPassed = true;
        
        if($checksPassed) {
            $su   = $this->session->get("_su");
            $user = $this->db->table("ChandlerUsers")->get($su ?? $token->user);
            if(!$user) return null;
            
            return new User($user, !is_null($su));
        }
        
        return null;
    }
    
    function authenticate(string $user): void
    {
        $this->session->set("tok", $this->makeToken($user, CONNECTING_IP, $_SERVER["HTTP_USER_AGENT"]));
    }
    
    function verifyCredentials(string $id, string $password): bool
    {
        $user = $this->db->table("ChandlerUsers")->get($id);
        if(!$user)
            return false;
        else if(!$this->verifyHash($password, $user->passwordHash))
            return false;
        
        return true;
    }
    
    function login(string $id, string $password): bool
    {
        if(!$this->verifyCredentials($id, $password))
            return false;
        
        $this->authenticate($id);
        return true;
    }
    
    function logout(bool $revoke = false): bool
    {
        $token = $this->session->get("tok");
        if(!$token) return false;
        
        if($revoke) $this->db->table("ChandlerTokens")->where("id", $token)->delete();
        
        $this->session->set("tok", NULL);
        
        return true;
    }
    
    use TSimpleSingleton;
}
