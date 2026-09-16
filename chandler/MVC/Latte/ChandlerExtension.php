<?php

declare(strict_types=1);

namespace Chandler\MVC\Latte;

use Latte\Extension;

class ChandlerExtension extends Extension
{
    public function __construct(
        private string $presenter,
    ) {}

    public function getTags(): array
    {
        return [
            'css' => CssNode::create(...),
            'script' => ScriptNode::create(...),
            'script_module' => ModuleScriptNode::create(...),
            'presenter' => PresenterNode::create(...),
        ];
    }

    public function getProviders(): array
    {
        return [
            'chandlerPresenter' => $this->presenter,
            'chandlerDomain' => explode("\\", $this->presenter)[0],
        ];
    }
}
