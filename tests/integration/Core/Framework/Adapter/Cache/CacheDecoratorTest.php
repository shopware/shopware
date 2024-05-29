<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheDecorator;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[CoversClass(CacheDecorator::class)]
#[Group('cache')]
class CacheDecoratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CacheDecorator $cache;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('cache_rework', $this);
        $cache = $this->getContainer()->get('cache.object');
        static::assertInstanceOf(CacheDecorator::class, $cache);
        $this->cache = $cache;
    }

    public function testTraceSave(): void
    {
        $collection = $this->getContainer()->get(CacheTagCollection::class);

        $this->cache->deleteItem('some-key');

        $collection->reset();

        $this->writeItem('some-key', ['tag-a', 'tag-b']);

        static::assertEquals(['tag-a', 'tag-b'], $collection->getTrace('all'));
    }

    public function testTraceRead(): void
    {
        $collection = $this->getContainer()->get(CacheTagCollection::class);

        $this->writeItem('some-key', ['tag-a', 'tag-b']);

        $collection->reset();
        $this->cache->getItem('some-key');

        static::assertEquals(['tag-a', 'tag-b'], $collection->getTrace('all'));
    }

    public function testTraceReadAndWrite(): void
    {
        $collection = $this->getContainer()->get(CacheTagCollection::class);

        $this->writeItem('some-key-1', ['tag-a', 'tag-b']);
        $this->writeItem('some-key-2', ['tag-c', 'tag-b']);

        $collection->reset();
        $this->cache->getItem('some-key-1');
        $this->cache->getItem('some-key-2');

        $this->writeItem('some-key-3', ['tag-d', 'tag-e']);

        static::assertEquals(['tag-a', 'tag-b', 'tag-c', 'tag-d', 'tag-e'], $collection->getTrace('all'));
    }

    /**
     * @param list<string> $tags
     */
    private function writeItem(string $key, array $tags): void
    {
        $item = $this->cache->getItem($key);
        $item->set($key);
        $item->tag($tags);

        $this->cache->save($item);
    }
}
