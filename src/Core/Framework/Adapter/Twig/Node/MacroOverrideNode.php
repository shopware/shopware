<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Node;

use Shopware\Core\Framework\Log\Package;
use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\CaptureNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\MacroNode;

#[Package('framework')]
#[YieldReady]
/**
 * @internal
 *
 * @codeCoverageIgnore - Covered by @see \Shopware\Tests\Integration\Core\Framework\Adapter\Twig\ReturnNodeTest
 */
class MacroOverrideNode extends MacroNode
{
    public function compile(Compiler $compiler): void
    {
        $compiler
            ->addDebugInfo($this)
            ->write(\sprintf('public function macro_%s(', $this->getAttribute('name')))
        ;

        /** @var ArrayExpression $arguments */
        $arguments = $this->getNode('arguments');
        foreach ($arguments->getKeyValuePairs() as $pair) {
            $name = $pair['key'];
            $default = $pair['value'];
            $compiler
                ->subcompile($name)
                ->raw(' = ')
                ->subcompile($default)
                ->raw(', ')
            ;
        }

        $compiler
            ->raw('...$varargs')
            ->raw(")\n") // Remove return type to allow return actual macro result
            ->write("{\n")
            ->indent()
            ->write("\$macros = \$this->macros;\n")
            ->write("\$context = [\n")
            ->indent()
        ;

        foreach ($arguments->getKeyValuePairs() as $pair) {
            $name = $pair['key'];
            /** @phpstan-ignore typePerfect.noMixedMethodCaller (code copied from twig, and twig relies on dynamic php) */
            $var = $name->getAttribute('name');
            if (str_starts_with($var, "\u{035C}")) {
                $var = substr($var, \strlen("\u{035C}"));
            }
            $compiler
                ->write('')
                ->string($var)
                ->raw(' => ')
                ->subcompile($name)
                ->raw(",\n")
            ;
        }

        $node = new CaptureNode($this->getNode('body'), $this->getNode('body')->lineno);

        $compiler
            ->write('')
            ->string(self::VARARGS_NAME)
            ->raw(' => ')
            ->raw("\$varargs,\n")
            ->outdent()
            ->write("] + \$this->env->getGlobals();\n\n")
            ->write("\$blocks = [];\n\n")
            ->write('$result =  ') // Store the result of the macro, instead of directly returning it
            ->subcompile($node)
            ->raw("\n")
            // customization to return actual class instead of markup
            ->write("if (SwTwigFunction::\$macroResult !== null) {\n")
            ->write('$result = SwTwigFunction::$macroResult;' . "\n")
            ->write('SwTwigFunction::$macroResult = null;' . "\n")
            ->write("}\n")
            ->write("return \$result;\n")
            // end of customization
            ->outdent()
            ->write("}\n\n")
        ;
    }
}
