<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig\Node;

use Shopware\Core\Framework\Twig\InheritanceExtension;
use Twig\Node\IncludeNode;
use Twig_Compiler;
use Twig_Node_Expression;

class SwInclude extends IncludeNode
{
    public function __construct(
        Twig_Node_Expression $expr,
        Twig_Node_Expression $variables = null,
        $only = false,
        $ignoreMissing = false,
        $lineno,
        $tag = null
    ) {
        parent::__construct($expr, $variables, $only, $ignoreMissing, $lineno, $tag);
    }

    protected function addGetTemplate(Twig_Compiler $compiler)
    {
        $compiler
            ->write('$finder = $this->env->getExtension(\'' . InheritanceExtension::class . '\')')
            ->raw("->getFinder();\n\n");

        $compiler
            ->write('$includeTemplate = $finder->find(')
            ->raw('$finder->getTemplateName(')
            ->subcompile($this->getNode('expr'))
            ->raw("));\n\n");

        $compiler
            ->write('$this->loadTemplate(($includeTemplate ?? null)')
            ->raw(', ')
            ->repr($this->getTemplateName())
            ->raw(', ')
            ->repr($this->getTemplateLine())
            ->raw(")\n\n")
        ;
    }
}
