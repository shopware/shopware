<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderLineItem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(OrderLineItemEntity::class)]
#[Package('checkout')]
class OrderLineItemEntityTest extends TestCase
{
    public function testJsonSerializeDoesNotExposePurchasePrices(): void
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setPayload([
            'purchasePrices' => 'hidden',
            'productNumber' => 'SW-1',
        ]);

        static::assertArrayHasKey('purchasePrices', $lineItem->getPayload());

        $serialized = $lineItem->jsonSerialize();

        static::assertArrayNotHasKey('purchasePrices', $serialized['payload']);
        static::assertSame('SW-1', $serialized['payload']['productNumber']);
    }
}
