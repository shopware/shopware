<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NodeVisitor;

use Shopware\Core\Framework\Adapter\Twig\SecurityExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;

/**
 * Wraps a callback operand so it is routed through the SecurityExtension policy at runtime.
 *
 * Twig operators such as "has some"/"has every" only reject non-closure callables in sandbox mode.
 * The App Script environment is not sandboxed, so this guard applies the allowed-function policy instead.
 *
 * @internal
 */
#[Package('framework')]
final class SecurityGuardedCallbackNode extends AbstractExpression
{
    public function __construct(AbstractExpression $callback, int $lineno)
    {
        parent::__construct(['callback' => $callback], [], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('$this->env->getExtension(\\' . SecurityExtension::class . '::class)->guardCallback(')
            ->subcompile($this->getNode('callback'))
            ->raw(')');
    }
}
