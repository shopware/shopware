<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Runtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntimeResetter;
use Twig\Runtime\EscaperRuntime;

/**
 * @internal
 */
#[CoversClass(CachedEscaperRuntimeResetter::class)]
class CachedEscaperRuntimeResetterTest extends TestCase
{
    protected function setUp(): void
    {
        CachedEscaperRuntime::resetEscapeCache();
    }

    protected function tearDown(): void
    {
        // Clean up static cache after each test
        CachedEscaperRuntime::resetEscapeCache();
    }

    public function testEscapeFilterCallsGetRuntimeAfterReset(): void
    {
        $escaper = new CachedEscaperRuntime(new EscaperRuntime());

        $escapeCacheProperty = new \ReflectionProperty($escaper, 'escapeCache');

        $cacheBefore = $escapeCacheProperty->getValue();
        static::assertSame([], $cacheBefore);

        // First call to populate the cache
        $escaper->escape('resetter_test_string', 'html', 'UTF-8');
        $cacheAfterFirstCall = $escapeCacheProperty->getValue();
        static::assertCount(1, $cacheAfterFirstCall);
        static::assertArrayHasKey('resetter_test_string', $cacheAfterFirstCall);

        // Reset the cache
        $resetter = new CachedEscaperRuntimeResetter();
        $resetter->reset();

        $cacheAfterReset = $escapeCacheProperty->getValue();
        static::assertSame([], $cacheAfterReset);

        // After reset, getRuntime should be called again
        $escaper->escape('resetter_test_string', 'html', 'UTF-8');
        $cacheAfterSecondCall = $escapeCacheProperty->getValue();
        static::assertCount(1, $cacheAfterSecondCall);
        static::assertArrayHasKey('resetter_test_string', $cacheAfterSecondCall);
    }
}
