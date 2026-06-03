<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerWishlistProductRemovedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerWishlistProductRemovedEvent::class)]
class CustomerWishlistProductRemovedEventTest extends TestCase
{
    public function testWebhookPayloadContract(): void
    {
        static::assertSame([
            'wishlistId' => ['type' => 'string'],
            'productId' => ['type' => 'string'],
            'customerId' => ['type' => 'string'],
        ], CustomerWishlistProductRemovedEvent::getAvailableData()->toArray());
    }

    public function testEventCarriesWishlistProductAndCustomer(): void
    {
        $wishlistId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $customerId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $event = new CustomerWishlistProductRemovedEvent($salesChannelContext, $wishlistId, $productId, $customerId);

        static::assertSame(CustomerWishlistProductRemovedEvent::EVENT_NAME, $event->getName());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $event->getContext());
        static::assertSame($salesChannelContext->getSalesChannelId(), $event->getSalesChannelId());
        static::assertSame($wishlistId, $event->getWishlistId());
        static::assertSame($productId, $event->getProductId());
        static::assertSame($customerId, $event->getCustomerId());
        static::assertSame([
            'wishlistId' => $wishlistId,
            'productId' => $productId,
            'customerId' => $customerId,
        ], $event->getValues());
    }
}
