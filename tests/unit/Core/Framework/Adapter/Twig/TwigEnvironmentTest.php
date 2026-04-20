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
        $twig = new TwigEnvironment(new ArrayLoader([]));

        $escaper = $twig->getRuntime(EscaperRuntime::class);
        static::assertInstanceOf(CachedEscaperRuntime::class, $escaper);

        $secondCallEscaper = $twig->getRuntime(EscaperRuntime::class);
        static::assertInstanceOf(CachedEscaperRuntime::class, $secondCallEscaper);

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

        $secondOtherRuntime = $twig->getRuntime(\stdClass::class);
        static::assertInstanceOf(\stdClass::class, $secondOtherRuntime);

        // Original Twig Environment also caches classes
        static::assertSame($otherRuntime, $secondOtherRuntime);
    }
}
