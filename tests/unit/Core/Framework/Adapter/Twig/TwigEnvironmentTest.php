<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Adapter\Twig\TwigEnvironment;
use Twig\Loader\ArrayLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
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

        static::assertStringContainsString('\Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::getAttribute', $code);
    }

    public function testGetRuntimeToReturnCachedEscaper(): void
    {
        $escaper = (new TwigEnvironment(new ArrayLoader([])))->getRuntime(EscaperRuntime::class);
        static::assertInstanceOf(CachedEscaperRuntime::class, $escaper);
    }

    public function testGetRuntimeCachesEscaperInstance(): void
    {
        $twig = new TwigEnvironment(new ArrayLoader([]));

        $escaper = $twig->getRuntime(EscaperRuntime::class);
        $secondCallEscaper = $twig->getRuntime(EscaperRuntime::class);

        // Assert internal caching of the class
        static::assertSame($escaper, $secondCallEscaper);
    }

    public function testGetRuntimeDelegatesOtherClasses(): void
    {
        $twig = new TwigEnvironment(new ArrayLoader([]));
        $twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
            public function load(string $class): ?\stdClass
            {
                if ($class === \stdClass::class) {
                    return new \stdClass();
                }

                return null;
            }
        });

        $otherRuntime = $twig->getRuntime(\stdClass::class);
        static::assertInstanceOf(\stdClass::class, $otherRuntime);
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
        $renderedTemplate = (new TwigEnvironment(new ArrayLoader(['test' => $template])))->render('test', ['names' => $names]);

        foreach ($names as $name) {
            static::assertStringContainsString('Hello ' . $name, $renderedTemplate);
        }
    }
}
