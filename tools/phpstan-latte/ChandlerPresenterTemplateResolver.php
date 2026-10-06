<?php

declare(strict_types=1);

namespace Chandler\PHPStanLatte;

use Efabrica\PHPStanLatte\LatteContext\LatteContext;
use Efabrica\PHPStanLatte\LatteTemplateResolver\AbstractClassTemplateResolver;
use Efabrica\PHPStanLatte\LatteTemplateResolver\LatteTemplateResolverResult;
use Efabrica\PHPStanLatte\PhpDoc\LattePhpDocResolver;
use Efabrica\PHPStanLatte\Resolver\LayoutResolver\LayoutPathResolver;
use Efabrica\PHPStanLatte\Template\Template;
use Efabrica\PHPStanLatte\Template\TemplateContext;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\RuleErrorBuilder;

use function dirname;
use function file_exists;
use function is_dir;
use function preg_replace;
use function strtolower;
use function substr;
use function ucfirst;

use const DIRECTORY_SEPARATOR;

/**
 * Resolves Latte templates rendered by Chandler presenters.
 *
 * Chandler renders templates from Router::delegateController() — it calls
 * IPresenter::render<Action>() and then renders the template named by the
 * render method (or by $this->template->_template when set). The default
 * path is <presenter dir>/templates/<PresenterName>/<Action>.latte.
 *
 * The roadmap also includes $this->template->_template overrides and
 * theme template paths (_templatePath); they are not handled yet.
 */
final class ChandlerPresenterTemplateResolver extends AbstractClassTemplateResolver
{
    private LayoutPathResolver $layoutPathResolver;

    public function __construct(
        LattePhpDocResolver $lattePhpDocResolver,
        ReflectionProvider $reflectionProvider,
        LayoutPathResolver $layoutPathResolver
    ) {
        parent::__construct($lattePhpDocResolver, $reflectionProvider);
        $this->layoutPathResolver = $layoutPathResolver;
    }

    public function getSupportedClasses(): array
    {
        return ['Chandler\MVC\IPresenter'];
    }

    /**
     * Router renders a template after the whole presenter lifecycle, so variables assigned
     * in onStartup() and onBeforeRender() belong to every template of the presenter.
     */
    protected function getClassGlobalTemplateContext(ClassReflection $classReflection, LatteContext $latteContext): TemplateContext
    {
        return parent::getClassGlobalTemplateContext($classReflection, $latteContext)
            ->union($latteContext->getMethodTemplateContext($classReflection->getName(), 'onStartup'))
            ->union($latteContext->getMethodTemplateContext($classReflection->getName(), 'onBeforeRender'));
    }

    protected function getClassResult(ClassReflection $classReflection, LatteContext $latteContext): LatteTemplateResolverResult
    {
        $result = new LatteTemplateResolverResult();
        if ($classReflection->isAbstract() || $classReflection->isAnonymous()) {
            return $result;
        }

        $classDir = $this->getClassDir($classReflection);
        if ($classDir === null) {
            return $result;
        }

        $presenterName = (string) preg_replace('/Presenter$/', '', $classReflection->getNativeReflection()->getShortName());
        $templatesBaseDir = (is_dir($classDir . DIRECTORY_SEPARATOR . 'templates') ? $classDir : dirname($classDir)) . DIRECTORY_SEPARATOR . 'templates';

        foreach ($this->getMethodsMatching($classReflection, '/^render.+$/') as $methodReflection) {
            if (!$methodReflection->isPublic()) {
                continue;
            }

            $methodName = $methodReflection->getName();
            $action = substr($methodName, 6);

            $templateContext = $this->getClassGlobalTemplateContext($classReflection, $latteContext)
                ->union($latteContext->getMethodTemplateContext($classReflection->getName(), $methodName));

            $templatePath = $this->findTemplate($templatesBaseDir, $presenterName, $action);
            if ($templatePath === null) {
                $mayRenderNothing = $latteContext->methodCallFinder()->hasAnyOutputCalls($classReflection->getName(), $methodName)
                    || $latteContext->methodCallFinder()->hasAnyTerminatingCalls($classReflection->getName(), $methodName)
                    || $latteContext->methodFinder()->hasAnyAlwaysTerminated($classReflection->getName(), $methodName);

                if (!$mayRenderNothing) {
                    $result->addErrorFromBuilder(RuleErrorBuilder::message("Cannot resolve latte template for {$classReflection->getNativeReflection()->getShortName()}::{$methodName}().")
                        ->identifier('latte.cannotResolve')
                        ->file($classReflection->getFileName() ?? 'unknown')
                        ->line($this->getMethodStartLine($classReflection, $methodName)));
                }
                continue;
            }

            $result->addTemplate(new Template($templatePath, $classReflection->getName(), $action, $templateContext));

            $layoutPath = $this->layoutPathResolver->resolve($templatePath);
            if ($layoutPath !== null && $layoutPath !== $templatePath) {
                $result->addTemplate(new Template($layoutPath, $classReflection->getName(), $action, $templateContext));
            }
        }

        return $result;
    }

    private function findTemplate(string $templatesBaseDir, string $presenterName, string $action): ?string
    {
        $templatesDir = $templatesBaseDir . DIRECTORY_SEPARATOR . $presenterName . DIRECTORY_SEPARATOR;

        // Router lowercases the route action and then applies ucfirst() to build the template name;
        // render methods keep their own casing, so both variants are tried.
        $candidates = [
            $templatesDir . $action . '.latte',
            $templatesDir . ucfirst(strtolower($action)) . '.latte',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
