<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Runtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntime;
use Shopware\Core\Framework\Adapter\Twig\Runtime\CachedEscaperRuntimeResetter;

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

    public function testResetOfCacheArray(): void
    {
        $callCount = 0;
        $cachedEscaperRuntime = new CachedEscaperRuntime();
        $cachedEscaperRuntime->setEscaper('test', static function (string $string) use (&$callCount): string {
            ++$callCount;

            return $string;
        });

        $cachedEscaperRuntime->escape('foo', 'test');
        $cachedEscaperRuntime->escape('foo', 'test');

        (new CachedEscaperRuntimeResetter())->reset();

        $cachedEscaperRuntime->escape('foo', 'test');
        $cachedEscaperRuntime->escape('foo', 'test');

        static::assertSame(2, $callCount, 'The inner runtime should be called once before and once after the reset');
    }
}
