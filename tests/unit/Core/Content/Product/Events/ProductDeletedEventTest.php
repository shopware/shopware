<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDeletedEvent::class)]
class ProductDeletedEventTest extends TestCase
{
    public function testWebhookPayloadContractExposesDeletionSnapshot(): void
    {
        static::assertSame([
            'productId' => ['type' => 'string'],
            'productNumber' => ['type' => 'string'],
            'deletedAt' => ['type' => 'string'],
        ], ProductDeletedEvent::getAvailableData()->toArray());
    }

    public function testDeletedProductIsNotEntityAwareSoFlowsCannotLoadRemovedRows(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new ProductDeletedEvent($context, $productId, '2024-01-01T00:00:00+00:00', 'SW-1000');

        static::assertSame(ProductDeletedEvent::EVENT_NAME, $event->getName());
        static::assertSame($context, $event->getContext());
        static::assertSame($productId, $event->getProductId());
        static::assertSame('SW-1000', $event->getProductNumber());
        static::assertSame('2024-01-01T00:00:00+00:00', $event->getDeletedAt());
        static::assertSame([
            'productId' => $productId,
            'productNumber' => 'SW-1000',
            'deletedAt' => '2024-01-01T00:00:00+00:00',
        ], $event->getValues());
    }
}
