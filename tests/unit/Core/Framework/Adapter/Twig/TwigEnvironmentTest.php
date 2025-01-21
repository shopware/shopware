<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\ReturnNodeTokenParser;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * @internal
 */
#[CoversClass(TwigEnvironment::class)]
class TwigEnvironmentTest extends TestCase
{
    public function testUsesShopwareFunctions(): void
    {
        $twig = new TwigEnvironment(new ArrayLoader(['bla' => '{{ test.bla }}']));

        $code = $twig->compileSource(new Source('{{ test.bla }}', 'bla'));

        static::assertStringContainsString('use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;', $code);
        static::assertStringContainsString('SwTwigFunction::getAttribute', $code);
    }

    public function testAddMacroResultCall(): void
    {
        $templateContent = <<<TWIG
{% macro example_macro() %}
    {% set foo = 'bar' %}
    {% return foo %}
{% endmacro %}
{{ _self.example_macro() }}
TWIG;

        $twig = new TwigEnvironment(new ArrayLoader(['template' => $templateContent]));
        $twig->addTokenParser(new ReturnNodeTokenParser());
        $source = new Source($templateContent, 'template');
        $stream = $twig->tokenize($source);
        $nodes = $twig->parse($stream);

        $code = $twig->compile($nodes);

        static::assertStringContainsString('yield SwTwigFunction::$macroResult;', $code);
    }
}
