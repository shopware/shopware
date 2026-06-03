<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductCreatedEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductCreatedEvent::class)]
class ProductCreatedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'product' => ['type' => 'entity', 'entityClass' => ProductDefinition::class, 'entityName' => ProductDefinition::ENTITY_NAME],
            'productId' => ['type' => 'string'],
        ], ProductCreatedEvent::getAvailableData()->toArray());
    }

    public function testProductIsLoadedLazilyAndOnlyOnce(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $loads = [];
        $product = new ProductEntity();
        $loader = static function () use (&$loads, $product): ProductEntity {
            $loads[] = true;

            return $product;
        };

        $event = new ProductCreatedEvent($context, $productId, $loader);

        static::assertSame(ProductCreatedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($productId, $event->getProductId());
        static::assertSame(['productId' => $productId], $event->getValues());
        static::assertCount(0, $loads);
        static::assertSame($product, $event->getProduct());
        static::assertSame($product, $event->getProduct());
        static::assertCount(1, $loads);
    }
}
