<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * @internal
 */
#[Package('framework')]
#[YieldReady]
final class RemoveDeprecatedBlocksNode extends Node
{
    /**
     * @param array<string, string> $blocks Block names mapped to their removal flags
     */
    public function __construct(private readonly array $blocks)
    {
        parent::__construct();
    }

    public function compile(Compiler $compiler): void
    {
        foreach ($this->blocks as $name => $flag) {
            $compiler
                ->write('if (\\' . Feature::class . '::has(')->string($flag)
                ->raw(') && \\' . Feature::class . '::isActive(')->string($flag)->raw(")) {\n")
                ->indent()
                ->write('unset($this->blocks[')->string($name)->raw("]);\n")
                ->outdent()
                ->write("}\n");
        }
    }
}
