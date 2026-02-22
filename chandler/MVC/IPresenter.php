<?php

declare(strict_types=1);

namespace Chandler\MVC;

use Latte\Engine as TemplatingEngine;

interface IPresenter
{
    public function getTemplatingEngine(): TemplatingEngine;
    public function getTemplateScope(): array;

    public function onStartup(): void;
    public function onBeforeRender(): void;
    public function onAfterRender(): void;
    public function onStop(): void;
    public function onDestruction(): void;
}
