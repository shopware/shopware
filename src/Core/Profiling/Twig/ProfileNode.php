<?php

namespace Shopware\Core\Profiling\Twig;

use Twig\Compiler;
use Twig\Node\Node;
use Twig\Attribute\YieldReady;

#[YieldReady]
class ProfileNode extends Node
{
    public function __construct($name, Node $body, $line, $tag = null)
    {
        parent::__construct(['body' => $body], ['name' => $name], $line, $tag);
    }

    public function compile(Compiler $compiler)
    {
        $profileName = $this->getAttribute('name');

        $compiler
            ->addDebugInfo($this)
            ->write("\Shopware\Core\Profiling\Profiler::start('{$profileName}', 'sw-template', []);\n")
            ->subcompile($this->getNode('body'))
            ->write("\Shopware\Core\Profiling\Profiler::stop('{$profileName}');\n")
        ;
    }
}
