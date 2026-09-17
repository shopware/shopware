<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NodeVisitor;

use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedAliasExpression;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\Template;

/**
 * @internal
 */
#[Package('framework')]
final class DeprecatedAliasNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof GetAttrExpression
            && $node->getAttribute('type') === Template::METHOD_CALL
            && $node->getNode('node') instanceof ContextVariable
            && $node->getNode('attribute') instanceof ConstantExpression
            && $node->getNode('attribute')->getAttribute('value') === 'silentUnwrap'
        ) {
            $node->getNode('node')->setAttribute('deprecated_alias_silent_unwrap', true);
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof ContextVariable
            || $node instanceof AssignContextVariable
            || $node->isDefinedTestEnabled()
            || $node->hasAttribute('deprecated_alias_silent_unwrap')
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
