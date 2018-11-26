<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Twig\Node;

use Shopware\Core\Framework\Twig\TemplateFinder;
use Twig_Compiler;
use Twig_Node_Expression;
use Twig_NodeOutputInterface;

/**
 * Represents an include node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SwInclude extends \Twig\Node\IncludeNode implements Twig_NodeOutputInterface
{
    /**
     * @var TemplateFinder
     */
    private $finder;

    public function __construct(Twig_Node_Expression $expr, Twig_Node_Expression $variables = null, $only = false, $ignoreMissing = false, $lineno, $tag = null, TemplateFinder $finder)
    {
        $this->finder = $finder;
        parent::__construct($expr, $variables, $only, $ignoreMissing, $lineno, $tag);
    }

    protected function addGetTemplate(Twig_Compiler $compiler)
    {
        $compiler
            ->write('$finder = $this->env->getExtension(\'Shopware\Core\Framework\Twig\InheritanceExtension\')')
            ->raw("->getFinder();\n\n");

        $compiler
            ->write('$includeTemplate = $finder->find(')
            ->raw('$finder->getTemplateName(')
            ->subcompile($this->getNode('expr'))
            ->raw("));\n\n");

        /*$srcExpression = $compiler->subcompile(();
        $src = $this->finder->find(
            $srcExpression,
                true
            );*/

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
