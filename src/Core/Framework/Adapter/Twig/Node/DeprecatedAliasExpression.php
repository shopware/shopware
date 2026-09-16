<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Adapter\Twig\Runtime\DeprecatedAlias;
use Shopware\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;

/**
 * @internal
 */
#[Package('framework')]
#[YieldReady]
final class DeprecatedAliasExpression extends AbstractExpression
{
    public function __construct(AbstractExpression $expression)
    {
        parent::__construct(['expression' => $expression], [], $expression->getTemplateLine());
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('\\' . DeprecatedAlias::class . '::resolve(')
            ->subcompile($this->getNode('expression'))
            ->raw(')');
    }
}
