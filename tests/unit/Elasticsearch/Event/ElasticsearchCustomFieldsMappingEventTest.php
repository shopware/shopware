<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElasticsearchCustomFieldsMappingEvent::class)]
class ElasticsearchCustomFieldsMappingEventTest extends TestCase
{
    public function testEvent(): void
    {
        $context = Context::createDefaultContext();
        $event = new ElasticsearchCustomFieldsMappingEvent(ProductDefinition::ENTITY_NAME, ['field1' => 'text'], $context);
        static::assertSame('product', $event->getEntity());
        static::assertSame($context, $event->getContext());

        static::assertSame('text', $event->getMapping('field1'));

        static::assertNull($event->getMapping('field2'));

        $event->setMapping('field2', 'text');

        static::assertSame('text', $event->getMapping('field2'));

        $event->removeMapping('field2');

        static::assertNull($event->getMapping('field2'));

        $mappings = $event->getMappings();

        static::assertSame(
            ['field1' => 'text'],
            $mappings
        );
    }
}
