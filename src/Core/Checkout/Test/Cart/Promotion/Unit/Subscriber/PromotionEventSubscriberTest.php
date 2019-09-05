<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Promotion\Subscriber\PromotionIndividualCodeRedeemer;

class PromotionEventSubscriberTest extends TestCase
{
    /**
     * This test verifies that our subscriber has the
     * correct event that its listening to.
     * This is important, because we have to ensure that
     * we save meta data in the payload of the line item
     * when the order is created.
     * This payload data helps us to reference used individual codes
     * with placed orders.
     *
     * @test
     * @group promotions
     */
    public function testSubscribeToOrderLineItemWritten(): void
    {
        $expectedEvent = CheckoutOrderPlacedEvent::class;

        // we need to have a key for the Shopware event
        static::assertArrayHasKey($expectedEvent, PromotionIndividualCodeRedeemer::getSubscribedEvents());
    }
}
