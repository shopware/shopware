<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductUpdatedEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductUpdatedEvent::class)]
class ProductUpdatedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'product' => ['type' => 'entity', 'entityClass' => ProductDefinition::class, 'entityName' => ProductDefinition::ENTITY_NAME],
            'productId' => ['type' => 'string'],
            'changedFields' => ['type' => 'array', 'of' => ['type' => 'string']],
        ], ProductUpdatedEvent::getAvailableData()->toArray());
    }

    public function testChangedFieldsAreExposedAsDeltaHint(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new ProductUpdatedEvent(
            $context,
            $productId,
            static fn (): ProductEntity => new ProductEntity(),
            ['stock', 'translation.name']
        );

        static::assertSame(ProductUpdatedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($productId, $event->getProductId());
        static::assertSame(['stock', 'translation.name'], $event->getChangedFields());
        static::assertSame([
            'productId' => $productId,
            'changedFields' => ['stock', 'translation.name'],
        ], $event->getValues());
    }
}
