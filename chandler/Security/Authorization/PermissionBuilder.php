<?php

declare(strict_types=1);

namespace Chandler\Security\Authorization;

class PermissionBuilder
{
    private $perm;
    private $permissionManager;

    public function __construct(?Permissions $permMan = null)
    {
        $this->perm = new Permission();

        $this->permissionManager = $permMan;
    }

    public function can(string $action): PermissionBuilder
    {
        $this->perm->action = $action;

        return $this;
    }

    public function model(string $model): PermissionBuilder
    {
        $this->perm->model = $model;

        return $this;
    }

    public function whichBelongsTo(?int $to)
    {
        $this->perm->context = $to;

        return is_null($this->permissionManager)
               ? $this
               : $this->permissionManager->hasPermission($this->build());
    }

    public function build(): Permission
    {
        return $this->perm;
    }
}
