<?php

declare(strict_types=1);

namespace Chandler\PHPStanLatte;

use Efabrica\PHPStanLatte\Analyser\AnalysedTemplatesRegistry;
use Efabrica\PHPStanLatte\Collector\CollectedData\CollectedResolvedNode;
use Efabrica\PHPStanLatte\LatteContext\LatteContext;
use Efabrica\PHPStanLatte\LatteTemplateResolver\CustomLatteTemplateResolverInterface;
use Efabrica\PHPStanLatte\LatteTemplateResolver\LatteTemplateResolverResult;
use Efabrica\PHPStanLatte\Template\Template;
use Efabrica\PHPStanLatte\Template\TemplateContext;
use Efabrica\PHPStanLatte\Template\Variable;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\StringType;
use PHPStan\Type\UnionType;

use function basename;
use function str_starts_with;

/**
 * Resolves framework error templates (@error*.latte).
 *
 * SimplePresenter::throwError() renders them with a fixed set of variables,
 * so there is no render call static analysis could follow. The variables
 * mirror the array passed to Latte by throwError().
 */
final class ChandlerErrorTemplateResolver implements CustomLatteTemplateResolverInterface
{
    private AnalysedTemplatesRegistry $analysedTemplatesRegistry;

    public function __construct(AnalysedTemplatesRegistry $analysedTemplatesRegistry)
    {
        $this->analysedTemplatesRegistry = $analysedTemplatesRegistry;
    }

    public function collect(): array
    {
        return [new CollectedResolvedNode(static::class, __FILE__, [])];
    }

    public function resolve(CollectedResolvedNode $resolvedNode, LatteContext $latteContext): LatteTemplateResolverResult
    {
        $result = new LatteTemplateResolverResult();

        $nullableString = new UnionType([new StringType(), new NullType()]);
        $templateContext = new TemplateContext([
            new Variable('code', new IntegerType()),
            new Variable('desc', new StringType()),
            new Variable('msg', new StringType()),
            new Variable('message', new StringType()),
            new Variable('errorCode', $nullableString),
            new Variable('errorId', $nullableString),
            new Variable('tracyCode', $nullableString),
        ]);

        foreach ($this->analysedTemplatesRegistry->getExistingTemplates() as $templatePath) {
            if (!str_starts_with(basename($templatePath), '@error')) {
                continue;
            }

            $result->addTemplate(new Template($templatePath, null, null, $templateContext));
        }

        return $result;
    }
}
