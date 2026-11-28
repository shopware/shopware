<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentDataLoaderResult::class)]
class ContentDataLoaderResultTest extends TestCase
{
    #[TestDox('noInput returns no data, is cache-aware, and records no tag')]
    public function testNoInput(): void
    {
        $result = ContentDataLoaderResult::noInput();

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('layoutOnly returns its data, is cache-aware, and records no tag')]
    public function testLayoutOnly(): void
    {
        $data = new ArrayStruct();

        $result = ContentDataLoaderResult::layoutOnly($data);

        static::assertSame($data, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('foundNothing returns no data, is cache-aware, and keeps every given tag in order')]
    public function testFoundNothing(): void
    {
        $result = ContentDataLoaderResult::foundNothing('tag-one', 'tag-two');

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame(['tag-one', 'tag-two'], $result->getCacheTags());
    }

    #[TestDox('foundNothingUncacheable returns no data and is not cache-aware')]
    public function testFoundNothingUncacheable(): void
    {
        $result = ContentDataLoaderResult::foundNothingUncacheable();

        static::assertFalse($result->hasData());
        static::assertFalse($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('failedExternally returns no data, is cache-aware, and records no tag')]
    public function testFailedExternally(): void
    {
        $result = ContentDataLoaderResult::failedExternally();

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('cached returns its data, is cache-aware, and keeps every given tag in order')]
    public function testCached(): void
    {
        $data = new ArrayStruct();

        $result = ContentDataLoaderResult::cached($data, 'tag-one', 'tag-two');

        static::assertSame($data, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame(['tag-one', 'tag-two'], $result->getCacheTags());
    }

    #[TestDox('cachedExternally returns its data, is cache-aware, and records no tag')]
    public function testCachedExternally(): void
    {
        $data = new ArrayStruct();

        $result = ContentDataLoaderResult::cachedExternally($data);

        static::assertSame($data, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('uncacheable returns its data and is not cache-aware')]
    public function testUncacheable(): void
    {
        $data = new ArrayStruct();

        $result = ContentDataLoaderResult::uncacheable($data);

        static::assertSame($data, $result->data);
        static::assertFalse($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }
}
