<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductStockChangedEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductStockChangedEvent::class)]
class ProductStockChangedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'product' => ['type' => 'entity', 'entityClass' => ProductDefinition::class, 'entityName' => ProductDefinition::ENTITY_NAME],
            'productId' => ['type' => 'string'],
            'stockChange' => ['type' => 'object', 'data' => null],
        ], ProductStockChangedEvent::getAvailableData()->toArray());
    }

    public function testStockChangeContainsOnlyKnownKeysPerProducingPath(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $loader = static fn (): ProductEntity => new ProductEntity();

        $orderDriven = new ProductStockChangedEvent($context, $productId, $loader, null, 3);
        static::assertSame(['stockDelta' => 3], $orderDriven->getStockChange());
        static::assertSame(['productId' => $productId, 'stockChange' => ['stockDelta' => 3]], $orderDriven->getValues());

        $directWrite = new ProductStockChangedEvent($context, $productId, $loader, 10);
        static::assertSame(['stock' => 10], $directWrite->getStockChange());
        static::assertSame($productId, $directWrite->getProductId());
        static::assertSame($context, $directWrite->getContext());
    }

    public function testProductIsLoadedLazilyAndOnlyOnce(): void
    {
        $loads = [];
        $product = new ProductEntity();
        $loader = static function () use (&$loads, $product): ProductEntity {
            $loads[] = true;

            return $product;
        };

        $event = new ProductStockChangedEvent(Context::createDefaultContext(), Uuid::randomHex(), $loader, 5);

        static::assertCount(0, $loads);
        static::assertSame($product, $event->getProduct());
        static::assertSame($product, $event->getProduct());
        static::assertCount(1, $loads);
    }
}
