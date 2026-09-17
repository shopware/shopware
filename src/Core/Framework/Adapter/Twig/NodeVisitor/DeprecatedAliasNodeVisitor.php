<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NodeVisitor;

use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedAliasExpression;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class DeprecatedAliasNodeVisitor implements NodeVisitorInterface
{
    private int $silentUnwrapDepth = 0;

    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof FunctionExpression && $node->getAttribute('name') === 'silentUnwrap') {
            ++$this->silentUnwrapDepth;
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof FunctionExpression && $node->getAttribute('name') === 'silentUnwrap') {
            --$this->silentUnwrapDepth;

            return $node;
        }

        if (!$node instanceof ContextVariable
            || $node instanceof AssignContextVariable
            || $node->isDefinedTestEnabled()
            || $this->silentUnwrapDepth > 0
        ) {
            return $node;
        }

        return new DeprecatedAliasExpression($node);
    }

    public function getPriority(): int
    {
        return 256;
    }
}
