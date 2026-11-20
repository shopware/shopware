<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderDeliveryProvider;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<OrderDeliveryProvider>
 */
#[Package('after-sales')]
#[CoversClass(OrderDeliveryProvider::class)]
class OrderDeliveryProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): OrderDeliveryProvider {
        return new OrderDeliveryProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return OrderDeliveryDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return [
            'positions.orderLineItem',
            'shippingMethod',
            'stateMachineState',
            'shippingOrderAddress.country',
            'shippingOrderAddress.countryState',
        ];
    }
}
