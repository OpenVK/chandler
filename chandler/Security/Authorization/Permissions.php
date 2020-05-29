<?php declare(strict_types=1);
namespace Chandler\Security\Authorization;
use Chandler\Database\DatabaseConnection;
use Chandler\Security\User;

class Permissions
{
    private $db;
    
    private $user;
    private $perms = [];
    
    function __construct(User $user)
    {
        $this->db   = DatabaseConnection::i()->getContext();
        $this->user = $user;
        
        $this->init();
    }
    
    private function init()
    {
        $uGroups = $this->user->getRaw()->related("ChandlerACLRelations.user")->order("priority ASC")->select("group");
        $groups  = array_map(function($j) {
            return $j->group;
        }, iterator_to_array($uGroups));
        
        $permissionsAllowed = $this->db->table("ChandlerACLGroupsPermissions")->where("group IN (?)", $groups);
        $permissionsDenied  = iterator_to_array((clone $permissionsAllowed)->where("status", false));
        $permissionsDenied  = array_merge($permissionsDenied, iterator_to_array($this->db->table("ChandlerACLUsersPermissions")->where("user", $this->user->getId())));
        $permissionsAllowed = $permissionsAllowed->where("status", true);
        
        foreach($permissionsAllowed as $perm) {
            foreach($permissionsDenied as $denied)
                if($denied->model === $perm->model && $denied->context === $perm->context && $denied->permission === $perm->permission)
                    continue 2;
            
            $pm = new Permission;
            $pm->action  = $perm->permission;
            $pm->model   = $perm->model;
            $pm->context = $perm->context;
            $pm->status  = true;
            
            $this->perms[] = $pm;
        }
    }
    
    function getPermissions(): array
    {
        return $this->perms;
    }
    
    function hasPermission(Permission $pm): bool
    {
        foreach($this->perms as $perm)
            if($perm->model === $pm->model && $perm->context === $pm->context && $perm->action === $pm->action)
                return true;
        
        return false;
    }
}
