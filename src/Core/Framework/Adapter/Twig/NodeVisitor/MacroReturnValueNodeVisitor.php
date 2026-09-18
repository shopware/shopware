<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NodeVisitor;

use Shopware\Core\Framework\Adapter\Twig\Node\MacroReturnValueExpression;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Routes every macro call through {@see MacroReturnValueExpression}, so that `{% return %}` inside
 * a macro yields the returned value at the call site instead of the rendered markup.
 *
 * @internal
 */
#[Package('framework')]
final class MacroReturnValueNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        // `is defined` compiles to an existence check that never runs the macro, so there is no value to pick up
        if (!$node instanceof MacroReferenceExpression || $node->isDefinedTestEnabled()) {
            return $node;
        }

        return new MacroReturnValueExpression($node);
    }

    public function getPriority(): int
    {
        return 0;
    }
}
