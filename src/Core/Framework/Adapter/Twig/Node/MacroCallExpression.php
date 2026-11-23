<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\MacroReferenceExpression;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Adapter\Twig\ReturnNodeTest
 */
#[Package('framework')]
final class MacroCallExpression extends AbstractExpression
{
    public function __construct(MacroReferenceExpression $macro)
    {
        parent::__construct(['macro' => $macro], [], $macro->getTemplateLine());
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('\\Shopware\\Core\\Framework\\Adapter\\Twig\\SwTwigFunction::callMacro(fn () => ')
            ->subcompile($this->getNode('macro'))
            ->raw(')')
        ;
    }
}
