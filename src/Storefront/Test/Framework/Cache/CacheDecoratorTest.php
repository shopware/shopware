<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheDecorator;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @group cache
 */
class CacheDecoratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var CacheDecorator
     */
    private $cache;

    protected function setUp(): void
    {
        $this->cache = $this->getContainer()->get('cache.object');
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
        $collection = $this->getContainer()->get(\Shopware\Core\Framework\Adapter\Cache\CacheTagCollection::class);

        $this->writeItem('some-key-1', ['tag-a', 'tag-b']);
        $this->writeItem('some-key-2', ['tag-c', 'tag-b']);

        $collection->reset();
        $this->cache->getItem('some-key-1');
        $this->cache->getItem('some-key-2');

        $this->writeItem('some-key-3', ['tag-d', 'tag-e']);

        static::assertEquals(['tag-a', 'tag-b', 'tag-c', 'tag-d', 'tag-e'], $collection->getTrace('all'));
    }

    private function writeItem(string $key, array $tags): void
    {
        $item = $this->cache->getItem($key);
        $item->set($key);
        $item->tag($tags);

        $this->cache->save($item);
    }
}
