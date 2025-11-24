<?php

declare(strict_types=1);

namespace Chandler\Security\Authorization;

class Permission
{
    public const CONTEXT_OWNER    = 0;
    public const CONTEXT_EVERYONE = 1;

    public const ACTION_READ      = "read";
    public const ACTION_EDIT      = "update";
    public const ACTION_DELETE    = "delete";

    public $action;
    public $model;
    public $context;
    public $status = true;
}
