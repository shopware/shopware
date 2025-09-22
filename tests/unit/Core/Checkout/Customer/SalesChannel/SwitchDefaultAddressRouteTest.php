<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\SwitchDefaultAddressRoute;
use Shopware\Core\Checkout\Customer\Event\CustomerSetDefaultBillingAddressEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerSetDefaultShippingAddressEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** @internal */
#[CoversClass(SwitchDefaultAddressRoute::class)]
class SwitchDefaultAddressRouteTest extends TestCase
{
    public function testSwapSetsDefaultBillingAddressAndReturnsAddresses(): void
    {
        $addressId = 'addr-1';
        $customerId = 'cust-1';
        $context = Context::createDefaultContext();

        $addressRepository = $this->createMock(EntityRepository::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $customer = new CustomerEntity();
        $customer->setId($customerId);

        $addressCollection = new CustomerAddressCollection();

        $addresses = $this->createMock(EntitySearchResult::class);
        $addresses->method('getEntities')->willReturn($addressCollection);

        // IdSearchResult expects array<array<string,mixed>> for the data argument
        $idSearchResult = new IdSearchResult(1, [['id' => $addressId]], new Criteria(), $context);

        $addressRepository->method('search')->willReturn($addresses);
        $addressRepository->method('searchIds')->willReturn($idSearchResult);

        $customerRepository->expects(self::once())
            ->method('update')
            ->with(
                self::callback(function (array $payload) use ($customerId, $addressId) {
                    if (count($payload) !== 1) {
                        return false;
                    }
                    $data = $payload[0];
                    return isset($data['id'], $data['defaultBillingAddressId'])
                        && $data['id'] === $customerId
                        && $data['defaultBillingAddressId'] === $addressId;
                }),
                $context
            );

        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CustomerSetDefaultBillingAddressEvent::class));

        $route = new SwitchDefaultAddressRoute($addressRepository, $customerRepository, $eventDispatcher);

        $response = $route->swap($addressId, 'billing', $salesChannelContext, $customer);

        // ensure we get a collection back (concrete equality check)
        self::assertSame($addressCollection, $response->getAddressCollection());
    }

    public function testSwapSetsDefaultShippingAddressAndReturnsAddresses(): void
    {
        $addressId = 'addr-2';
        $customerId = 'cust-2';
        $context = Context::createDefaultContext();

        $addressRepository = $this->createMock(EntityRepository::class);
        $customerRepository = $this->createMock(EntityRepository::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        $customer = new CustomerEntity();
        $customer->setId($customerId);

        $addressCollection = new CustomerAddressCollection();

        $addresses = $this->createMock(EntitySearchResult::class);
        $addresses->method('getEntities')->willReturn($addressCollection);

        $idSearchResult = new IdSearchResult(1, [['id' => $addressId]], new Criteria(), $context);

        $addressRepository->method('search')->willReturn($addresses);
        $addressRepository->method('searchIds')->willReturn($idSearchResult);

        $customerRepository->expects(self::once())
            ->method('update')
            ->with(
                self::callback(function (array $payload) use ($customerId, $addressId) {
                    if (count($payload) !== 1) {
                        return false;
                    }
                    $data = $payload[0];
                    return isset($data['id'], $data['defaultShippingAddressId'])
                        && $data['id'] === $customerId
                        && $data['defaultShippingAddressId'] === $addressId;
                }),
                $context
            );

        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CustomerSetDefaultShippingAddressEvent::class));

        $route = new SwitchDefaultAddressRoute($addressRepository, $customerRepository, $eventDispatcher);

        $response = $route->swap($addressId, 'shipping', $salesChannelContext, $customer);

        self::assertSame($addressCollection, $response->getAddressCollection());
    }
}