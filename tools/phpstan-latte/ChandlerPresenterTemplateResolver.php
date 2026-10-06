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

use function array_filter;
use function array_unique;
use function array_values;
use function dirname;
use function file_exists;
use function in_array;
use function is_dir;
use function is_file;
use function ltrim;
use function preg_replace;
use function str_ends_with;
use function strtolower;
use function substr;
use function ucfirst;

use const DIRECTORY_SEPARATOR;

/**
 * Resolves Latte templates rendered by Chandler presenters.
 *
 * Chandler renders templates from Router::delegateController(): it calls
 * IPresenter::render<Action>(), then renders the template named by
 * $this->template->_template (when set and the file exists) or the default
 * <presenter dir>/templates/<PresenterName>/<Action>.latte.
 *
 * Templates rendered directly through $engine->render() (for example the
 * error pages in OpenVKPresenter::onStartup()) are collected as well.
 *
 * Theme template paths (_templatePath) are not handled yet.
 */
final class ChandlerPresenterTemplateResolver extends AbstractClassTemplateResolver
{
    private const TEMPLATE_CONTROL_VARIABLES = ['_template', '_templatePath'];

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

        $this->addExplicitRenderTemplates($result, $classReflection, $latteContext);

        foreach ($this->getMethodsMatching($classReflection, '/^render.+$/') as $methodReflection) {
            if (!$methodReflection->isPublic()) {
                continue;
            }

            $methodName = $methodReflection->getName();
            $action = substr($methodName, 6);

            $rawTemplateContext = $this->getClassGlobalTemplateContext($classReflection, $latteContext)
                ->union($latteContext->getMethodTemplateContext($classReflection->getName(), $methodName));
            $templateContext = $this->withoutTemplateControlVariables($rawTemplateContext);

            [$templatePaths, $mayUseDefault] = $this->resolveTemplateOverrides($rawTemplateContext, $templatesBaseDir);
            if ($mayUseDefault || $templatePaths === []) {
                $defaultTemplate = $this->findTemplate($templatesBaseDir, $presenterName, $action);
                if ($defaultTemplate !== null) {
                    $templatePaths[] = $defaultTemplate;
                }
            }

            if ($templatePaths === []) {
                if (!$this->methodMayRenderNothing($latteContext, $classReflection->getName(), $methodName)) {
                    $result->addErrorFromBuilder(RuleErrorBuilder::message("Cannot resolve latte template for {$classReflection->getNativeReflection()->getShortName()}::{$methodName}().")
                        ->identifier('latte.cannotResolve')
                        ->file($classReflection->getFileName() ?? 'unknown')
                        ->line($this->getMethodStartLine($classReflection, $methodName)));
                }
                continue;
            }

            foreach ($templatePaths as $templatePath) {
                $this->addTemplateWithLayout($result, $templatePath, $classReflection, $action, $templateContext);
            }
        }

        return $result;
    }

    private function addExplicitRenderTemplates(LatteTemplateResolverResult $result, ClassReflection $classReflection, LatteContext $latteContext): void
    {
        foreach ($this->getMethodsMatching($classReflection, '/^(render|onStartup|onBeforeRender)/') as $methodReflection) {
            if (!$methodReflection->isPublic()) {
                continue;
            }

            $methodName = $methodReflection->getName();
            $templateRenders = $latteContext->templateRenderFinder()->find($classReflection->getName(), $methodName);
            if ($templateRenders === []) {
                continue;
            }

            $templateContext = $this->withoutTemplateControlVariables(
                $this->getClassGlobalTemplateContext($classReflection, $latteContext)
                    ->union($latteContext->getMethodTemplateContext($classReflection->getName(), $methodName))
            );

            foreach ($templateRenders as $templateRender) {
                $templatePath = $templateRender->getTemplatePath();
                if ($templatePath === null || !is_file($templatePath)) {
                    continue;
                }

                $this->addTemplateWithLayout(
                    $result,
                    $templatePath,
                    $classReflection,
                    $methodName,
                    $templateContext
                        ->mergeVariables($templateRender->getVariables())
                        ->mergeComponents($templateRender->getComponents())
                );
            }
        }
    }

    private function addTemplateWithLayout(LatteTemplateResolverResult $result, string $templatePath, ClassReflection $classReflection, string $action, TemplateContext $templateContext): void
    {
        $result->addTemplate(new Template($templatePath, $classReflection->getName(), $action, $templateContext));

        $layoutPath = $this->layoutPathResolver->resolve($templatePath);
        if ($layoutPath !== null && $layoutPath !== $templatePath) {
            $result->addTemplate(new Template($layoutPath, $classReflection->getName(), $action, $templateContext));
        }
    }

    /**
     * @return array{0: string[], 1: bool} resolved template paths and whether the default template may be rendered too
     */
    private function resolveTemplateOverrides(TemplateContext $templateContext, string $templatesBaseDir): array
    {
        $templatePaths = [];
        $mayUseDefault = true;

        foreach ($templateContext->getVariables() as $variable) {
            if ($variable->getName() !== '_template') {
                continue;
            }

            $mayUseDefault = $variable->mightBeUndefined();

            foreach ($variable->getType()->getConstantStrings() as $constantString) {
                $template = $constantString->getValue();
                if ($template === '') {
                    continue;
                }

                $candidate = $template[0] === '/' ? $template : $templatesBaseDir . DIRECTORY_SEPARATOR . ltrim($template, '/');
                if (!is_file($candidate) && !str_ends_with($candidate, '.latte')) {
                    $candidate .= '.latte';
                }
                if (is_file($candidate)) {
                    $templatePaths[] = $candidate;
                }
            }
        }

        return [array_values(array_unique($templatePaths)), $mayUseDefault];
    }

    private function withoutTemplateControlVariables(TemplateContext $templateContext): TemplateContext
    {
        return $templateContext->withVariables(array_filter(
            $templateContext->getVariables(),
            static fn($variable): bool => !in_array($variable->getName(), self::TEMPLATE_CONTROL_VARIABLES, true)
        ));
    }

    private function methodMayRenderNothing(LatteContext $latteContext, string $className, string $methodName): bool
    {
        return $latteContext->methodCallFinder()->hasAnyOutputCalls($className, $methodName)
            || $latteContext->methodCallFinder()->hasAnyTerminatingCalls($className, $methodName)
            || $latteContext->methodFinder()->hasAnyAlwaysTerminated($className, $methodName);
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
