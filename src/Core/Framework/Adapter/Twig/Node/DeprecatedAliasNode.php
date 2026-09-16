<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Adapter\Twig\Runtime\DeprecatedAlias;
use Shopware\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\Node;

/**
 * @internal
 */
#[Package('framework')]
#[YieldReady]
final class DeprecatedAliasNode extends Node
{
    public function __construct(string $alias, string $removedIn, string $replacedBy, int $line)
    {
        parent::__construct(
            ['value' => new ContextVariable($replacedBy, $line)],
            [
                'alias' => $alias,
                'removed_in' => $removedIn,
                'replaced_by' => $replacedBy,
                'message' => \sprintf('The "%s" Twig variable is deprecated. Use "%s" instead.', $alias, $replacedBy),
            ],
            $line,
        );
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write('$context[')
            ->string($this->getAttribute('alias'))
            ->raw('] = new \\' . DeprecatedAlias::class . '(')
            ->subcompile($this->getNode('value'))
            ->raw(', ')
            ->string($this->getAttribute('removed_in'))
            ->raw(', ')
            ->string($this->getAttribute('message'))
            ->raw(");\n");
    }
}
