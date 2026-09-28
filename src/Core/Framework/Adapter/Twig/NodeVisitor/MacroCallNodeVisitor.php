<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NodeVisitor;

use Shopware\Core\Framework\Adapter\Twig\Node\MacroCallExpression;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * @internal
 */
#[Package('framework')]
final class MacroCallNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof MacroReferenceExpression) {
            return new MacroCallExpression($node);
        }

        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
