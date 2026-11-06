<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\SelectedDeliveryShippingMethodsSubscriber;
use Shopware\Core\Checkout\Cart\Delivery\Struct\SelectedDeliveryShippingMethods;
use Shopware\Core\Checkout\Cart\Event\BeforeCartMergeEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SelectedDeliveryShippingMethodsSubscriber::class)]
class SelectedDeliveryShippingMethodsSubscriberTest extends TestCase
{
    public function testGuestSelectionWinsForTheDeliveriesBothCartsSelectedFor(): void
    {
        $customerCart = new Cart('customer');
        $customerCart->addExtension(
            SelectedDeliveryShippingMethods::KEY,
            new SelectedDeliveryShippingMethods(['warehouse-a' => 'standard', 'warehouse-b' => 'standard'])
        );

        $guestCart = new Cart('guest');
        $guestCart->addExtension(
            SelectedDeliveryShippingMethods::KEY,
            new SelectedDeliveryShippingMethods(['warehouse-b' => 'express'])
        );

        (new SelectedDeliveryShippingMethodsSubscriber())->onBeforeCartMerge($this->createEvent($customerCart, $guestCart));

        static::assertSame(
            ['warehouse-a' => 'standard', 'warehouse-b' => 'express'],
            $customerCart->getExtensionOfType(SelectedDeliveryShippingMethods::KEY, SelectedDeliveryShippingMethods::class)?->getShippingMethodIds(),
        );
    }

    public function testGuestSelectionIsAddedToACustomerCartWithoutOne(): void
    {
        $customerCart = new Cart('customer');

        $guestCart = new Cart('guest');
        $guestCart->addExtension(
            SelectedDeliveryShippingMethods::KEY,
            new SelectedDeliveryShippingMethods(['warehouse-b' => 'express'])
        );

        (new SelectedDeliveryShippingMethodsSubscriber())->onBeforeCartMerge($this->createEvent($customerCart, $guestCart));

        static::assertSame(
            ['warehouse-b' => 'express'],
            $customerCart->getExtensionOfType(SelectedDeliveryShippingMethods::KEY, SelectedDeliveryShippingMethods::class)?->getShippingMethodIds(),
        );
    }

    public function testCustomerSelectionIsKeptWithoutAGuestSelection(): void
    {
        $customerSelection = new SelectedDeliveryShippingMethods(['warehouse-a' => 'standard']);
        $customerCart = new Cart('customer');
        $customerCart->addExtension(SelectedDeliveryShippingMethods::KEY, $customerSelection);

        (new SelectedDeliveryShippingMethodsSubscriber())->onBeforeCartMerge($this->createEvent($customerCart, new Cart('guest')));

        static::assertSame($customerSelection, $customerCart->getExtension(SelectedDeliveryShippingMethods::KEY));
    }

    private function createEvent(Cart $customerCart, Cart $guestCart): BeforeCartMergeEvent
    {
        return new BeforeCartMergeEvent($customerCart, $guestCart, new LineItemCollection(), Generator::generateSalesChannelContext());
    }
}
