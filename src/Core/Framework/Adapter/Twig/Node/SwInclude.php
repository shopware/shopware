<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\IncludeNode;

/**
 * @internal
 *
 * Retained for the v6.8.0.0 deprecation window only. Once the v6.8.0.0 feature flag is
 * permanent, the token parsers emit a stock Twig IncludeNode wrapping a
 * {@see FinderTemplateExpression} and this node is removed.
 */
#[Package('framework')]
#[YieldReady]
class SwInclude extends IncludeNode
{
    protected function addGetTemplate(Compiler $compiler): void
    {
        $compiler
            ->write("((function () use (\$context, \$blocks) {\n")
            ->indent()
                ->write('$finder = $this->env->getExtension(\'' . NodeExtension::class . '\')->getFinder();')->raw("\n\n")
                ->write('$includeTemplate = $finder->find(')
                        ->subcompile($this->getNode('expr'))
                ->raw(");\n\n")
                ->write('return $this->load(')
                    ->raw('$includeTemplate ?? null, ')
                    ->repr($this->getTemplateLine())
                ->raw(");\n")
            ->outdent()
            ->write('})())');
    }
}
