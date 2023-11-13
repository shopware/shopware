<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexConfigEvent;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexConfigEvent
 */
class ElasticsearchIndexConfigEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ElasticsearchIndexConfigEvent('index', ['config' => 'value'], $this->createMock(AbstractElasticsearchDefinition::class), Context::createDefaultContext());
        static::assertSame('index', $event->getIndexName());
        static::assertSame(['config' => 'value'], $event->getConfig());
        static::assertInstanceOf(AbstractElasticsearchDefinition::class, $event->getDefinition());
        static::assertInstanceOf(Context::class, $event->getContext());

        $event->setConfig(['config' => 'value2']);

        static::assertSame(['config' => 'value2'], $event->getConfig());
    }
}
