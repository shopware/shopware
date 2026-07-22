<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;
use Twig\Node\NodeOutputInterface;

#[Package('framework')]
#[YieldReady]
#[BecomesInternal(version: 'v6.8.0')]
class ReturnNode extends Node implements NodeOutputInterface
{
    public function compile(Compiler $compiler): void
    {
        $compiler->addDebugInfo($this);

        if ($this->hasNode('expr')) {
            $compiler->raw('\Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::$macroResult = ');
            $compiler->subcompile($this->getNode('expr'));
            $compiler->raw(";\n");
        }
        $compiler->write("return;\n");
    }
}
