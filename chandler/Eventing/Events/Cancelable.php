<?php

declare(strict_types=1);

namespace Chandler\Eventing\Events;

interface Cancelable
{
    protected $cancelled;

    public function cancel(): void;

    public function isCancelled(): bool;
}
