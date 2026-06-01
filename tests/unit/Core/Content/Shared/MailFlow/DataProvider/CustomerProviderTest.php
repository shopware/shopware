<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerProvider;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<CustomerProvider>
 */
#[Package('after-sales')]
#[CoversClass(CustomerProvider::class)]
class CustomerProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): CustomerProvider {
        return new CustomerProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return CustomerDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return [
            'salutation',
            'defaultBillingAddress.country',
            'defaultBillingAddress.countryState',
            'defaultBillingAddress.salutation',
            'defaultShippingAddress.country',
            'defaultShippingAddress.countryState',
            'defaultShippingAddress.salutation',
        ];
    }
}
