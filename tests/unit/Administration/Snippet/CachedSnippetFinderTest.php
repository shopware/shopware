<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\CachedSnippetFinder;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(CachedSnippetFinder::class)]
class CachedSnippetFinderTest extends TestCase
{
    private MockObject&SnippetFinder $snippetFinder;

    private MockObject&AdapterInterface $cache;

    protected function setUp(): void
    {
        $this->snippetFinder = $this->createMock(SnippetFinder::class);
        $this->cache = $this->createMock(AdapterInterface::class);
    }

    public function testFindSnippetsAssignSnippetsToCache(): void
    {
        $snippets = ['test-snippet-1', 'test-snippet-2'];

        $cacheItem = $this->buildCacheItem(false, true);
        $cacheItem->set(null);

        $this->cache->expects(static::once())->method('getItem')->willReturn($cacheItem);
        $this->snippetFinder->expects(static::once())->method('findSnippets')->willReturn($snippets);

        $cachedSnippetFinder = new CachedSnippetFinder($this->snippetFinder, $this->cache);
        $result = $cachedSnippetFinder->findSnippets('test');

        static::assertEquals($snippets, $cacheItem->get());
        static::assertSame($snippets, $result);
    }

    public function testFindSnippetsReturnsCachedSnippets(): void
    {
        $snippets = ['test-snippet-1', 'test-snippet-2'];

        $cacheItem = $this->buildCacheItem(true, false);
        $cacheItem->set($snippets);

        $this->cache->expects(static::once())->method('getItem')->willReturn($cacheItem);
        $this->snippetFinder->expects(static::never())->method('findSnippets');

        $cachedSnippetFinder = new CachedSnippetFinder($this->snippetFinder, $this->cache);
        $result = $cachedSnippetFinder->findSnippets('test');

        static::assertSame($snippets, $result);
    }

    protected function buildCacheItem(bool $isHit, bool $isTaggable): CacheItem
    {
        $cacheItem = new CacheItem();
        $prop = ReflectionHelper::getProperty(CacheItem::class, 'key');
        $prop->setValue($cacheItem, 'admin_snippet_test');
        $prop = ReflectionHelper::getProperty(CacheItem::class, 'isHit');
        $prop->setValue($cacheItem, $isHit);
        $prop = ReflectionHelper::getProperty(CacheItem::class, 'isTaggable');
        $prop->setValue($cacheItem, $isTaggable);

        return $cacheItem;
    }
}
