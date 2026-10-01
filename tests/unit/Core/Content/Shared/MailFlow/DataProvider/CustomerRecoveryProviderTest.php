<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerRecoveryProvider;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<CustomerRecoveryProvider>
 */
#[Package('after-sales')]
#[CoversClass(CustomerRecoveryProvider::class)]
class CustomerRecoveryProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): CustomerRecoveryProvider {
        return new CustomerRecoveryProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return CustomerRecoveryDefinition::ENTITY_NAME;
    }

    protected function getExpectedAssociations(): array
    {
        return ['customer.salutation'];
    }
}
