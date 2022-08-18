<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexAliasSwitchedEvent;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexAliasSwitchedEvent
 */
class ElasticsearchIndexAliasSwitchedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ElasticsearchIndexAliasSwitchedEvent(['alias' => 'index']);
        static::assertSame(['alias' => 'index'], $event->getChanges());
    }
}
