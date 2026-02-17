<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ContentDataLoaderResult::class)]
class ContentDataLoaderResultTest extends TestCase
{
    #[TestDox('notFound returns no data, is cache-aware, and has empty tags')]
    public function testNotFound(): void
    {
        $result = ContentDataLoaderResult::notFound();

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('uncacheable returns data, is not cache-aware, and has empty tags')]
    public function testUncacheable(): void
    {
        $result = ContentDataLoaderResult::uncacheable(new ArrayStruct());

        static::assertTrue($result->hasData());
        static::assertFalse($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('cached returns data, is cache-aware, and has specific tags')]
    public function testCached(): void
    {
        $result = ContentDataLoaderResult::cached(new ArrayStruct(), 'tag-one', 'tag-two');

        static::assertTrue($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame(['tag-one', 'tag-two'], $result->getCacheTags());
    }

    #[TestDox('cachedExternally returns data, is cache-aware, and has empty tags')]
    public function testCachedExternally(): void
    {
        $result = ContentDataLoaderResult::cachedExternally(new ArrayStruct());

        static::assertTrue($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }
}
