<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<OrderProvider>
 */
#[Package('after-sales')]
#[CoversClass(OrderProvider::class)]
class OrderProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): OrderProvider {
        return new OrderProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return [
            'primaryOrderDelivery',
            'primaryOrderTransaction',
            'orderCustomer',
            'orderCustomer.salutation',
            'lineItems.downloads.media',
            'lineItems.cover',
            'deliveries.shippingMethod',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingOrderAddress.countryState',
            'stateMachineState',
            'transactions.stateMachineState',
            'transactions.paymentMethod',
            'deliveries.stateMachineState',
            'currency',
            'addresses.country',
            'addresses.countryState',
            'tags',
            'documents',
        ];
    }

    protected function assertAdditionalCriteria(Criteria $criteria): void
    {
        $transactionCriteria = $criteria->getAssociations()['transactions'] ?? null;
        static::assertInstanceOf(Criteria::class, $transactionCriteria);
        static::assertCount(1, $transactionCriteria->getSorting());
        static::assertSame('createdAt', $transactionCriteria->getSorting()[0]->getField());
    }
}
