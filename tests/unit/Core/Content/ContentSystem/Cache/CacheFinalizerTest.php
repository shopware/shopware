<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Content\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CacheFinalizer::class)]
class CacheFinalizerTest extends TestCase
{
    #[TestDox('forwards cache tags to collector when cache is enabled')]
    public function testFinalizeForwardsTagsToCollector(): void
    {
        $request = new Request();
        $cacheContext = new RenderingCacheContext();
        $cacheContext->addTags(['tag-a', 'tag-b']);

        $collector = $this->createMock(CacheTagCollector::class);
        $collector->expects($this->once())->method('addTag')->with('tag-a', 'tag-b');

        $finalizer = new CacheFinalizer($collector);
        $finalizer->finalize($request, $cacheContext);
    }

    #[TestDox('does not set HTTP cache attribute when cache is enabled')]
    public function testFinalizeDoesNotSetHttpCacheAttributeWhenCacheIsEnabled(): void
    {
        $request = new Request();
        $cacheContext = new RenderingCacheContext();
        $cacheContext->addTags(['tag-a', 'tag-b']);

        $collector = static::createStub(CacheTagCollector::class);
        $finalizer = new CacheFinalizer($collector);
        $finalizer->finalize($request, $cacheContext);

        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_HTTP_CACHE));
    }

    #[TestDox('sets cache disabled attribute when cache is disabled')]
    public function testFinalizeSetsCacheDisabledAttribute(): void
    {
        $request = new Request();
        $cacheContext = new RenderingCacheContext();
        $cacheContext->disable();

        $collector = static::createStub(CacheTagCollector::class);
        $finalizer = new CacheFinalizer($collector);
        $finalizer->finalize($request, $cacheContext);

        static::assertFalse($request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE));
    }
}
