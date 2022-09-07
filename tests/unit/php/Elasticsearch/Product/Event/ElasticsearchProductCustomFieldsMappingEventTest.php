<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Product\Event\ElasticsearchProductCustomFieldsMappingEvent;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Product\Event\ElasticsearchProductCustomFieldsMappingEvent
 */
class ElasticsearchProductCustomFieldsMappingEventTest extends TestCase
{
    public function testEvent(): void
    {
        $context = Context::createDefaultContext();
        $event = new ElasticsearchProductCustomFieldsMappingEvent(['field1' => 'text'], $context);
        static::assertSame($context, $event->getContext());

        static::assertSame('text', $event->getMapping('field1'));

        static::assertNull($event->getMapping('field2'));

        $event->setMapping('field2', 'text');

        static::assertSame('text', $event->getMapping('field2'));

        $event->removeMapping('field2');

        static::assertNull($event->getMapping('field2'));

        $mappings = $event->getMappings();

        static::assertEquals(
            ['field1' => 'text'],
            $mappings
        );
    }
}
