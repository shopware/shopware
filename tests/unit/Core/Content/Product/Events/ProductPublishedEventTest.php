<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductPublishedEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPublishedEvent::class)]
class ProductPublishedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'product' => ['type' => 'entity', 'entityClass' => ProductDefinition::class, 'entityName' => ProductDefinition::ENTITY_NAME],
            'productId' => ['type' => 'string'],
            'active' => ['type' => 'bool'],
        ], ProductPublishedEvent::getAvailableData()->toArray());
    }

    public function testEventReportsProductAsActive(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new ProductPublishedEvent($context, $productId, static fn (): ProductEntity => new ProductEntity());

        static::assertSame(ProductPublishedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($productId, $event->getProductId());
        static::assertTrue($event->isActive());
        static::assertSame(['productId' => $productId, 'active' => true], $event->getValues());
    }
}
