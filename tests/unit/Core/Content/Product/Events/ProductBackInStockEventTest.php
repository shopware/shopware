<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductBackInStockEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductBackInStockEvent::class)]
class ProductBackInStockEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'product' => ['type' => 'entity', 'entityClass' => ProductDefinition::class, 'entityName' => ProductDefinition::ENTITY_NAME],
            'productId' => ['type' => 'string'],
            'available' => ['type' => 'bool'],
        ], ProductBackInStockEvent::getAvailableData()->toArray());
    }

    public function testEventReportsProductAsAvailable(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new ProductBackInStockEvent($context, $productId, static fn (): ProductEntity => new ProductEntity());

        static::assertSame(ProductBackInStockEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($productId, $event->getProductId());
        static::assertTrue($event->isAvailable());
        static::assertSame(['productId' => $productId, 'available' => true], $event->getValues());
    }
}
