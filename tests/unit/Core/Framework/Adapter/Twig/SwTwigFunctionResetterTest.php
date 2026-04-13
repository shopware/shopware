<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunctionResetter;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[CoversClass(SwTwigFunctionResetter::class)]
class SwTwigFunctionResetterTest extends TestCase
{
    protected function setUp(): void
    {
        SwTwigFunction::resetEscapeCache();
    }

    protected function tearDown(): void
    {
        // Clean up static cache after each test
        SwTwigFunction::resetEscapeCache();
    }

    public function testEscapeFilterCallsGetRuntimeAfterReset(): void
    {
        $escaper = new EscaperRuntime();
        $escapeCacheProperty = new \ReflectionProperty(new SwTwigFunction(), 'escapeCache');

        $cacheBefore = $escapeCacheProperty->getValue();
        static::assertSame([], $cacheBefore);

        // First call to populate the cache
        SwTwigFunction::escapeFilter($escaper, 'resetter_test_string', 'html', 'UTF-8');
        $cacheAfterFirstCall = $escapeCacheProperty->getValue();
        static::assertCount(1, $cacheAfterFirstCall);
        static::assertArrayHasKey('resetter_test_string', $cacheAfterFirstCall);

        // Reset the cache
        $resetter = new SwTwigFunctionResetter();
        $resetter->reset();

        $cacheAfterReset = $escapeCacheProperty->getValue();
        static::assertSame([], $cacheAfterReset);

        // After reset, getRuntime should be called again
        SwTwigFunction::escapeFilter($escaper, 'resetter_test_string', 'html', 'UTF-8');
        $cacheAfterSecondCall = $escapeCacheProperty->getValue();
        static::assertCount(1, $cacheAfterSecondCall);
        static::assertArrayHasKey('resetter_test_string', $cacheAfterSecondCall);
    }
}
