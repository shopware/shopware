<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Twig\Compiler;
use Twig\Node\IncludeNode;

class SwInclude extends IncludeNode
{
    protected function addGetTemplate(Compiler $compiler): void
    {
        $compiler
            ->write("((function () use (\$context, \$blocks) {\n")
            ->indent()
                ->write('$finder = $this->env->getExtension(\'' . NodeExtension::class . '\')->getFinder();')->raw("\n\n")
                ->write('$includeTemplate = $finder->find(')
                    ->raw('$finder->getTemplateName(')
                        ->subcompile($this->getNode('expr'))
                    ->raw(')')
                ->raw(");\n\n")
                ->write('return $this->loadTemplate(')
                    ->raw('$includeTemplate ?? null, ')
                    ->repr($this->getTemplateName())->raw(', ')
                    ->repr($this->getTemplateLine())
                ->raw(");\n")
            ->outdent()
            ->write('})())');
    }
}
