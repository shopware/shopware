<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\IncludeNode;

/**
 * @internal
 *
 * Resolves a (possibly dynamic) template name through Shopware's bundle/theme
 * inheritance hierarchy at runtime, so a stock {@see IncludeNode}
 * can load the resolved template without overriding its compiler.
 */
#[Package('framework')]
final class FinderTemplateExpression extends AbstractExpression
{
    public function __construct(AbstractExpression $name, int $line)
    {
        parent::__construct(['name' => $name], [], $line);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->raw('$this->env->getExtension(')
            ->string(NodeExtension::class)
            ->raw(')->getFinder()->find(')
            ->subcompile($this->getNode('name'))
            ->raw(')');
    }
}
