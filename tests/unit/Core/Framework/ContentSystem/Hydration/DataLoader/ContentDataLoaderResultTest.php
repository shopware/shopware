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

    #[TestDox('uncacheable with data returns its data and is not cache-aware')]
    public function testUncacheableWithData(): void
    {
        $data = new ArrayStruct();

        $result = ContentDataLoaderResult::uncacheable($data);

        static::assertSame($data, $result->data);
        static::assertFalse($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('uncacheable without data returns no data and is not cache-aware')]
    public function testUncacheableWithoutData(): void
    {
        $result = ContentDataLoaderResult::uncacheable();

        static::assertFalse($result->hasData());
        static::assertFalse($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('noInput without data returns no data, is cache-aware, and records no tag')]
    public function testNoInputWithoutData(): void
    {
        $result = ContentDataLoaderResult::noInput();

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('noInput with data returns its data, is cache-aware, and records no tag')]
    public function testNoInputWithData(): void
    {
        $data = new ArrayStruct();

        $result = ContentDataLoaderResult::noInput($data);

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

    #[TestDox('failedExternally returns no data, is cache-aware, and records no tag')]
    public function testFailedExternally(): void
    {
        $result = ContentDataLoaderResult::failedExternally();

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }
}
