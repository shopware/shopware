<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\CachedSnippetFinder;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CachedSnippetFinder::class)]
class CachedSnippetFinderTest extends TestCase
{
    private TagAwareAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new TagAwareAdapter(new ArrayAdapter());
    }

    public function testFindSnippetsAssignSnippetsToCache(): void
    {
        $snippets = ['test-snippet-1', 'test-snippet-2'];

        $snippetFinder = $this->createMock(SnippetFinder::class);
        $snippetFinder->expects($this->once())
            ->method('findSnippets')
            ->willReturnCallback(static function (string $locale) use ($snippets): array {
                static::assertSame('test', $locale);

                return $snippets;
            });

        $cachedSnippetFinder = new CachedSnippetFinder($snippetFinder, $this->cache);
        $result = $cachedSnippetFinder->findSnippets('test');

        static::assertSame($snippets, $result);

        $cacheItem = $this->cache->getItem('admin_snippet_test');
        static::assertTrue($cacheItem->isHit());
        static::assertSame($snippets, $cacheItem->get());
    }

    public function testFindSnippetsReturnsCachedSnippets(): void
    {
        $snippets = ['test-snippet-1', 'test-snippet-2'];

        $cacheItem = $this->cache->getItem('admin_snippet_test');
        $cacheItem->set($snippets);
        $this->cache->save($cacheItem);

        $snippetFinder = $this->createMock(SnippetFinder::class);
        $snippetFinder->expects($this->never())->method('findSnippets');

        $cachedSnippetFinder = new CachedSnippetFinder($snippetFinder, $this->cache);
        $result = $cachedSnippetFinder->findSnippets('test');

        static::assertSame($snippets, $result);
    }

    public function testInvalidatingTheCacheTagForcesANewLookup(): void
    {
        $snippetFinder = $this->createMock(SnippetFinder::class);
        $snippetFinder->expects($this->exactly(2))
            ->method('findSnippets')
            ->willReturn(['test-snippet-1']);

        $cachedSnippetFinder = new CachedSnippetFinder($snippetFinder, $this->cache);
        $cachedSnippetFinder->findSnippets('test');
        $cachedSnippetFinder->findSnippets('test');

        $this->cache->invalidateTags([CachedSnippetFinder::CACHE_TAG]);

        static::assertSame(['test-snippet-1'], $cachedSnippetFinder->findSnippets('test'));
    }
}
