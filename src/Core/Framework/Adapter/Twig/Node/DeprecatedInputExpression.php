<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Adapter\Twig\Runtime\DeprecatedInputRuntime;
use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;

/**
 * @internal
 */
#[Package('framework')]
final class DeprecatedInputExpression extends AbstractExpression
{
    public function __construct(
        AbstractExpression $expression,
        string $removedIn,
        string $message,
    ) {
        parent::__construct(
            ['expression' => $expression],
            ['removed_in' => $removedIn, 'message' => $message],
            $expression->getTemplateLine(),
        );
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('\\' . DeprecatedInputRuntime::class . '::access(')
            ->string($this->getAttribute('removed_in'))
            ->raw(', ')
            ->string($this->getAttribute('message'))
            ->raw(', fn () => ')
            ->subcompile($this->getNode('expression'))
            ->raw(')');
    }
}
