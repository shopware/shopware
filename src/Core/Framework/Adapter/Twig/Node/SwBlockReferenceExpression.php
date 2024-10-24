<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\BlockReferenceExpression;
use Twig\Node\Node;

#[Package('core')]
#[YieldReady]
class SwBlockReferenceExpression extends AbstractExpression
{
    public function __construct(Node $name, ?Node $template, int $lineno)
    {
        $nodes = ['name' => $name];
        if (null !== $template) {
            $nodes['template'] = $template;
        }

        parent::__construct($nodes, ['is_defined_test' => false, 'output' => false], $lineno);
    }

    /**
     * @see BlockReferenceExpression::compile
     */
    public function compile(Compiler $compiler): void
    {
        if ($this->getAttribute('is_defined_test')) {
            $this->compileTemplateCall($compiler, 'hasBlock');
        } else {
            if ($this->getAttribute('output')) {
                $compiler->addDebugInfo($this);

                $compiler->write('yield from ');
                $this
                    ->compileTemplateCall($compiler, 'yieldBlock')
                    ->raw(";\n");
            } else {
                $this->compileTemplateCall($compiler, 'renderBlock');
            }
        }
    }

    private function compileTemplateCall(Compiler $compiler, string $method): Compiler
    {
        if (!$this->hasNode('template')) {
            $compiler->write('$this');
        } else {
            $compiler
                ->write("((function () use (\$context, \$blocks) {\n")
                ->indent()
                    ->write('$finder = $this->env->getExtension(\'' . NodeExtension::class . '\')->getFinder();')->raw("\n\n")
                    ->write('$includeTemplate = $finder->find(')
                        ->subcompile($this->getNode('template'))
                    ->raw(");\n\n")
                    ->write('return $this->loadTemplate(')
                        ->raw('$includeTemplate ?? null, ')
                        ->repr($this->getTemplateName())->raw(', ')
                        ->repr($this->getTemplateLine())
                    ->raw(");\n")
                ->outdent()
                ->write('})())')
            ;
        }

        $compiler->raw(\sprintf('->unwrap()->%s', $method));

        return $this->compileBlockArguments($compiler);
    }

    private function compileBlockArguments(Compiler $compiler): Compiler
    {
        $compiler
            ->raw('(')
                ->subcompile($this->getNode('name'))
            ->raw(', $context');

        if (!$this->hasNode('template')) {
            $compiler->raw(', $blocks');
        }

        return $compiler->raw(')');
    }
}
