<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\NodeVisitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\Node\MacroReturnValueExpression;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\MacroReturnValueNodeVisitor;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Expression\Test\DefinedTest;
use Twig\Node\Node;
use Twig\Node\SetNode;
use Twig\Source;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MacroReturnValueNodeVisitor::class)]
class MacroReturnValueNodeVisitorTest extends TestCase
{
    private Environment $env;

    protected function setUp(): void
    {
        $this->env = new Environment(new ArrayLoader());
        $this->env->addExtension(new PhpSyntaxExtension());
    }

    public function testMacroCallIsWrapped(): void
    {
        $module = $this->parse('{% macro foo() %}{% endmacro %}{% set bar = _self.foo() %}');

        $set = $this->find($module, SetNode::class);
        static::assertInstanceOf(SetNode::class, $set);

        $value = $this->find($set->getNode('values'), MacroReturnValueExpression::class);
        static::assertInstanceOf(MacroReturnValueExpression::class, $value);
        static::assertInstanceOf(MacroReferenceExpression::class, $value->getNode('macro'));
    }

    public function testDefinedTestIsNotWrapped(): void
    {
        $module = $this->parse('{% macro foo() %}{% endmacro %}{% set bar = _self.foo is defined %}');

        $test = $this->find($module, DefinedTest::class);
        static::assertInstanceOf(DefinedTest::class, $test);
        static::assertInstanceOf(MacroReferenceExpression::class, $test->getNode('node'));
        static::assertNull($this->find($module, MacroReturnValueExpression::class));
    }

    public function testUnrelatedNodesAreUntouched(): void
    {
        $visitor = new MacroReturnValueNodeVisitor();
        $node = new ConstantExpression(1, 1);

        static::assertSame($node, $visitor->enterNode($node, $this->env));
        static::assertSame($node, $visitor->leaveNode($node, $this->env));
        static::assertSame(0, $visitor->getPriority());
    }

    #[DataProvider('renderProvider')]
    public function testRenderedMacroCalls(string $template, string $expected): void
    {
        $this->env->setLoader(new ArrayLoader(['template' => $template]));

        static::assertSame($expected, trim($this->env->render('template', ['x' => 2])));
    }

    public static function renderProvider(): \Generator
    {
        yield 'returned array replaces the markup' => [
            '{% sw_macro_function pair() %}{% return [1, 2] %}{% end_sw_macro_function %}{% set foo = _self.pair() %}{{ foo[1] }}',
            '2',
        ];
        yield 'returned value prints directly' => [
            '{% sw_macro_function one() %}{% return 1 %}{% end_sw_macro_function %}{{ _self.one() }}',
            '1',
        ];
        yield 'macro without return keeps its markup' => [
            '{% sw_macro_function markup() %}markup{% end_sw_macro_function %}{{ _self.markup() }}',
            'markup',
        ];
        yield 'plain macro can return as well' => [
            '{% macro branch(x) %}{% if x == 1 %}{% return 1 %}{% else %}{% return 2 %}{% endif %}{% endmacro %}{{ _self.branch(x) }}',
            '2',
        ];
        yield 'nested macro calls keep their own values' => [
            '{% sw_macro_function inner() %}{% return 5 %}{% end_sw_macro_function %}{% sw_macro_function outer() %}{% set v = _self.inner() %}{% return v + 1 %}{% end_sw_macro_function %}{{ _self.outer() }}-{{ _self.inner() }}',
            '6-5',
        ];
        yield 'markup call after a returning call is not polluted' => [
            '{% sw_macro_function one() %}{% return 1 %}{% end_sw_macro_function %}{% sw_macro_function markup() %}markup{% end_sw_macro_function %}{{ _self.one() }}{{ _self.markup() }}',
            '1markup',
        ];
    }

    private function parse(string $template): Node
    {
        return $this->env->parse($this->env->tokenize(new Source($template, 'template')));
    }

    /**
     * @param class-string<Node> $class
     */
    private function find(Node $node, string $class): ?Node
    {
        if ($node instanceof $class) {
            return $node;
        }

        foreach ($node as $child) {
            $found = $this->find($child, $class);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
