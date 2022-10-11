<?php declare(strict_types=1);
namespace Chandler\Security;
use Chandler\Database\DatabaseConnection;
use Chandler\Security\Authorization\Permissions;
use Chandler\Security\Authorization\PermissionBuilder;
use Nette\Database\Table\ActiveRow;
use Nette\Database\UniqueConstraintViolationException;

/**
 * User class.
 * 
 * @author kurotsun <celestine@vriska.ru>
 */
class User
{
    /**
     * @var \Nette\Database\Context DB Explorer
     */
    private $db;
    
    /**
     * @var \Nette\Database\Table\ActiveRow ActiveRow that represents user
     */
    private $user;
    /**
     * @var bool Does this user is not the one who is logged in, but substituted?
     */
    private $tainted;
    
    /**
     * @param \Nette\Database\Table\ActiveRow $user ActiveRow that represents user
     * @param bool $tainted Does this user is not the one who is logged in, but substituted?
     */
    function __construct(ActiveRow $user, bool $tainted = false)
    {
        $this->db      = DatabaseConnection::i()->getContext();
        $this->user    = $user;
        $this->tainted = $tainted;
    }
    
    /**
     * Computes hash for a password.
     * 
     * @param string $password password
     * @return string hash
     */
    private static function makeHash(string $password): string
    {
        $salt = openssl_random_pseudo_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $hash = sodium_crypto_pwhash(
            16,
            $password,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );
        
        return bin2hex($hash) . "$" . bin2hex($salt);
    }
    
    /**
     * Get user's GUID.
     * 
     * @return string GUID
     */
    function getId(): string
    {
        return $this->user->id;
    }
    
    /**
     * Get user's DB data as an array.
     * 
     * @return array DB data in form of associative array
     */
    function getAttributes(): array
    {
        return (array) $this->user;
    }
    
    /**
     * Get Permission Manager object.
     * 
     * @api
     * @see \Chandler\Security\User::can
     * @return \Chandler\Security\Authorization\Permissions Permission Manager
     */
    function getPermissions(): Permissions
    {
        return new Permissions($this);
    }
    
    /**
     * Get ActiveRow that represents user
     * 
     * @return \Nette\Database\Table\ActiveRow ActiveRow
     */
    function getRaw(): ActiveRow
    {
        return $this->user;
    }
    
    /**
     * Checks if this user is not the one who is logged in, but substituted
     * 
     * @return bool Does this user is not the one who is logged in, but substituted?
     */
    function isTainted(): bool
    {
        return $this->tainted;
    }
    
    /**
     * Begins to build permission for checking it's status using Permission Builder.
     * To get permission status you should chain methods like this:
     *     $user->can('do something')->model('\app\Web\Models\MyModel')->whichBelongsTo(10);
     * In this case whichBelongsTo will automatically build permission and check if user
     * has it. If you need to build permission for something another use {@see \Chandler\Security\User::getPermissions}.
     * 
     * @api
     * @uses \Chandler\Security\Authorization\PermissionBuilder::can
     * @return \Chandler\Security\Authorization\Permissions Permission Manager
     */
    function can(string $action): PermissionBuilder
    {
        $pb = new PermissionBuilder($this->getPermissions());
        
        return $pb->can($action);
    }
    
    /**
     * Updates user password.
     * If $oldPassword parameter is passed it will update password only if current
     * user password (not the new one) matches $oldPassword.
     * 
     * @api
     * @param string $password New Password
     * @param string|null $oldPassword Current Password
     * @return bool False if token manipulation error has been thrown
     */
    function updatePassword(string $password, ?string $oldPassword = NULL): bool
    {
        if(!is_null($oldPassword))
            if(!Authenticator::verifyHash($oldPassword, $this->getRaw()->passwordHash))
                return false;
        
        $users = DatabaseConnection::i()->getContext()->table("ChandlerUsers");
        $users->where("id", $this->getId())->update([
            "passwordHash" => $this->makeHash($password),
        ]);
        
        return true;
    }
    
    /**
     * Creates new user if login has not been taken yet.
     * 
     * @api
     * @param string $login Login (usually an email)
     * @param string $password Password
     * @return self|null New user if successful, null otherwise
     */
    static function create(string $login, string $password): ?User
    {
        $users = DatabaseConnection::i()->getContext()->table("ChandlerUsers");
        $hash  = self::makeHash($password);
        
        try {
            $users->insert([
                "login"        => $login,
                "passwordHash" => $hash,
            ]);
            
            $user = $users->where("login", $login)->fetch();
        } catch(UniqueConstraintViolationException $ex) {
            return null;
        }
        
        return new static($user);
    }
}
