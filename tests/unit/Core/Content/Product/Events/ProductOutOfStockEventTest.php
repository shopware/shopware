<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductOutOfStockEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductOutOfStockEvent::class)]
class ProductOutOfStockEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'product' => ['type' => 'entity', 'entityClass' => ProductDefinition::class, 'entityName' => ProductDefinition::ENTITY_NAME],
            'productId' => ['type' => 'string'],
            'available' => ['type' => 'bool'],
        ], ProductOutOfStockEvent::getAvailableData()->toArray());
    }

    public function testEventReportsProductAsUnavailable(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new ProductOutOfStockEvent($context, $productId, static fn (): ProductEntity => new ProductEntity());

        static::assertSame(ProductOutOfStockEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($productId, $event->getProductId());
        static::assertFalse($event->isAvailable());
        static::assertSame(['productId' => $productId, 'available' => false], $event->getValues());
    }
}
