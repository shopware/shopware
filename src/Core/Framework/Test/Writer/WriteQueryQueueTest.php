<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Writer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderState\OrderStateDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class WriteQueryQueueTest extends TestCase
{
    public function test_set_and_update_order_integrate_the_new_values(): void
    {
        $queue = new WriteCommandQueue();

        $queue->setOrder(
            OrderDefinition::class
        );

        $queue->updateOrder(
            OrderTransactionDefinition::class
        );

        self::assertEquals(
            [
                OrderCustomerDefinition::class,
                OrderStateDefinition::class,
                PaymentMethodDefinition::class,
                CurrencyDefinition::class,
                SalesChannelDefinition::class,
                OrderAddressDefinition::class,
                OrderDefinition::class,
                OrderDeliveryDefinition::class,
                OrderLineItemDefinition::class,
                OrderTransactionStateDefinition::class,
                OrderTransactionDefinition::class,
            ], $queue->getOrder());
    }
}
