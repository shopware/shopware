<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\Node\MacroReturnValueExpression;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MacroReturnValueExpression::class)]
class MacroReturnValueExpressionTest extends TestCase
{
    public function testCompilesTheMacroCallIntoTheResultWrapper(): void
    {
        $template = '{% sw_macro_function foo() %}{% end_sw_macro_function %}{{ _self.foo() }}';
        $env = new Environment(new ArrayLoader(['template' => $template]));
        $env->addExtension(new PhpSyntaxExtension());

        $compiled = $env->compileSource(new Source($template, 'template'));

        static::assertStringContainsString('\\' . SwTwigFunction::class . '::callMacro(fn () => ', $compiled);
        // the receiver of the call differs between Twig versions, the wrapped call itself does not
        static::assertMatchesRegularExpression('/::callMacro\\(fn \\(\\) => \\$(this|macros\\["_self"\\])->\\w+\\(/', $compiled);
    }
}
