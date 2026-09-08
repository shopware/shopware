<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NodeVisitor;

use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\Binary\HasEveryBinary;
use Twig\Node\Expression\Binary\HasSomeBinary;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Guards the callback operand of the "has some" and "has every" operators.
 *
 * Twig's own callable check only fires in sandbox mode, so these operators would otherwise execute
 * arbitrary string callables (e.g. `[...] has some "system"`) in the non-sandboxed App Script environment.
 * The filters map/filter/reduce/sort/find are guarded directly in SecurityExtension instead.
 *
 * @internal
 */
#[Package('framework')]
final class CallbackOperatorSecurityNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        if (!$node instanceof HasSomeBinary && !$node instanceof HasEveryBinary) {
            return $node;
        }

        $callback = $node->getNode('right');
        if ($callback instanceof SecurityGuardedCallbackNode || !$callback instanceof AbstractExpression) {
            return $node;
        }

        $node->setNode('right', new SecurityGuardedCallbackNode($callback, $callback->getTemplateLine()));

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
