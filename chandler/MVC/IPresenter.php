<?php declare(strict_types=1);
namespace Chandler\MVC;
use Latte\Engine as TemplatingEngine;

interface IPresenter
{
    function getTemplatingEngine(): TemplatingEngine;
    function getTemplateScope(): array;
    
    function onStartup(): void;
    function onBeforeRender(): void;
    function onAfterRender(): void;
    function onStop(): void;
    function onDestruction(): void;
}
