<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexingFinishedEvent;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ElasticsearchOptimizeSwitch::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class ElasticsearchOptimizeSwitchTest extends TestCase
{
    public function testNoSubscribersInMajorMode(): void
    {
        Feature::withFeatureEnabled('v6.8.0.0', static function (): void {
            static::assertSame([], ElasticsearchOptimizeSwitch::getSubscribedEvents());
        });
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testDirectInvocationThrowsInMajorMode(): void
    {
        $storage = new ArrayKeyValueStorage([]);
        $subscriber = new ElasticsearchOptimizeSwitch($storage);
        $this->expectException(FeatureException::class);

        Feature::withFeatureEnabled('v6.8.0.0', static fn () => $subscriber->onIndexingFinished(new ElasticsearchIndexingFinishedEvent()));
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
