<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\BlockReferenceNode;

/**
 * @internal
 */
#[Package('framework')]
#[YieldReady]
final class DeprecatedBlockReferenceNode extends BlockReferenceNode
{
    public function __construct(string $name, private readonly string $flag, int $line)
    {
        parent::__construct($name, $line);
    }

    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write('if (!\\' . Feature::class . '::has(')->string($this->flag)
            ->raw(') || !\\' . Feature::class . '::isActive(')->string($this->flag)->raw(")) {\n")
            ->indent();

        parent::compile($compiler);

        $compiler->outdent()->write("}\n");
    }
}
