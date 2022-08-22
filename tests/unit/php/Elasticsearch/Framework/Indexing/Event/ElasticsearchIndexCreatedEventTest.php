<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexCreatedEvent;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexCreatedEvent
 */
class ElasticsearchIndexCreatedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ElasticsearchIndexCreatedEvent('index', $this->createMock(AbstractElasticsearchDefinition::class));
        static::assertSame('index', $event->getIndexName());
        static::assertInstanceOf(AbstractElasticsearchDefinition::class, $event->getDefinition());
    }
}
