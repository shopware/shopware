<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Twig\Loader\ArrayLoader;
use Twig\Source;

/**
 * @internal
 */
#[CoversClass(TwigEnvironment::class)]
class TwigEnvironmentTest extends TestCase
{
    public function testUsesShopwareGetAttributeFunctionAndCachedEscaperRuntime(): void
    {
        $code = (new TwigEnvironment(new ArrayLoader(['bla' => '{{ test.bla }}'])))
            ->compileSource(new Source('{{ test.bla }}', 'bla'));

        static::assertStringContainsString('\Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::getAttribute', $code);
        static::assertStringContainsString('\Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime::escape($this->env->getRuntime(\'Twig\\Runtime\\EscaperRuntime\'),', $code);
    }

    public function testMarkupEscapeIsWorkingCorrectly(): void
    {
        $template = <<<'TWIG'
{% for name in names %}
    {% set captured %}{{ name }}{% endset %}
    Hello {{ captured|trim|e }}
{% endfor %}
TWIG;

        $names = [
            'John Doe',
            'Jane Doe',
            'Peter Doe',
            'Hans Doe',
            'Harald Doe',
            'Will Doe',
        ];
        $renderedTemplate = (new TwigEnvironment(new ArrayLoader(['test' => $template])))
            ->render('test', ['names' => $names]);

        foreach ($names as $name) {
            static::assertStringContainsString('Hello ' . $name, $renderedTemplate);
        }
    }
}
