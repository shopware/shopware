<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexingFinishedEvent;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ElasticsearchOptimizeSwitch::class)]
class ElasticsearchOptimizeSwitchTest extends TestCase
{
    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);
    }

    public function testGetSubscribers(): void
    {
        $subscribers = ElasticsearchOptimizeSwitch::getSubscribedEvents();

        static::assertSame([
            ElasticsearchIndexingFinishedEvent::class => 'onIndexingFinished',
        ], $subscribers);
    }

    public function testOnIndexingFinished(): void
    {
        $storage = new ArrayKeyValueStorage([]);

        static::assertFalse($storage->has(ElasticsearchOptimizeSwitch::FLAG));

        $event = new ElasticsearchIndexingFinishedEvent();
        $subscriber = new ElasticsearchOptimizeSwitch($storage);
        $subscriber->onIndexingFinished($event);

        static::assertTrue($storage->get(ElasticsearchOptimizeSwitch::FLAG));
    }
}
