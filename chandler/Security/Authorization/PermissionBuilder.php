<?php declare(strict_types=1);
namespace Chandler\Security\Authorization;

class PermissionBuilder
{
    private $perm;
    private $permissionManager;
    
    function __construct(?Permissions $permMan = NULL)
    {
        $this->perm = new Permission;
        
        $this->permissionManager = $permMan;
    }
    
    function can(string $action): PermissionBuilder
    {
        $this->perm->action = $action;
        
        return $this;
    }
    
    function model(string $model): PermissionBuilder
    {
        $this->perm->model = $model;
        
        return $this;
    }
    
    function whichBelongsTo(?int $to)
    {
        $this->perm->context = $to;
        
        return is_null($this->permissionManager)
               ? $this
               : $this->permissionManager->hasPermission($this->build());
    }
    
    function build(): Permission
    {
        return $this->perm;
    }
}
