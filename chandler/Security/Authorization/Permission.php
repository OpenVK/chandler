<?php declare(strict_types=1);
namespace Chandler\Security\Authorization;

class Permission
{
    const CONTEXT_OWNER    = 0;
    const CONTEXT_EVERYONE = 1;
    
    const ACTION_READ      = "read";
    const ACTION_EDIT      = "update";
    const ACTION_DELETE    = "delete";
    
    public $action;
    public $model;
    public $context;
    public $status = true;
}
