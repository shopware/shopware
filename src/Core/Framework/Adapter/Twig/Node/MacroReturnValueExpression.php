<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\MacroReferenceExpression;

/**
 * Wraps a macro call so that a value handed over by `{% return %}` replaces the rendered markup.
 *
 * Twig types the macro call path as string|Markup, so the macro itself cannot hand the value back;
 * {@see ReturnNode} parks it in {@see SwTwigFunction::$macroResult} and this node picks it up
 * right after the call returns.
 *
 * @internal
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Adapter\Twig\ReturnNodeTest
 */
#[Package('framework')]
#[YieldReady]
final class MacroReturnValueExpression extends AbstractExpression
{
    public function __construct(MacroReferenceExpression $macro)
    {
        parent::__construct(['macro' => $macro], [], $macro->getTemplateLine());
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('\\' . SwTwigFunction::class . '::callMacro(fn () => ')
            ->subcompile($this->getNode('macro'))
            ->raw(')')
        ;
    }
}
