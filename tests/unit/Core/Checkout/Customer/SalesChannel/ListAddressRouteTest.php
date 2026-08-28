<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Event\AddressListingCriteriaEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\ListAddressRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ListAddressRoute::class)]
class ListAddressRouteTest extends TestCase
{
    /**
     * @var Stub&SalesChannelRepository<CustomerAddressCollection>
     */
    private Stub&SalesChannelRepository $addressRepository;

    private CollectingEventDispatcher $eventDispatcher;

    private ListAddressRoute $route;

    protected function setUp(): void
    {
        $this->addressRepository = static::createStub(SalesChannelRepository::class);
        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->route = new ListAddressRoute(
            $this->addressRepository,
            $this->eventDispatcher
        );
    }

    public function testGetDecoratedThrowsException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->route->getDecorated();
    }

    public function testLoad(): void
    {
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();
        $customer = $context->getCustomer();
        static::assertNotNull($customer);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(
            new CustomerAddressCollection([$context->getShippingLocation()->getAddress() ?? new CustomerAddressEntity()])
        );

        /** @var MockObject&SalesChannelRepository<CustomerAddressCollection> $addressRepository */
        $addressRepository = $this->createMock(SalesChannelRepository::class);
        $addressRepository->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria) {
                    return $criteria->hasAssociation('salutation')
                        && $criteria->hasAssociation('country')
                        && $criteria->hasAssociation('countryState');
                }),
                $context
            )
            ->willReturn($searchResult);

        $route = new ListAddressRoute($addressRepository, $this->eventDispatcher);

        $response = $route->load($criteria, $context, $customer);

        static::assertCount(1, $response->getAddressCollection());

        $events = $this->eventDispatcher->getEvents();
        static::assertInstanceOf(AddressListingCriteriaEvent::class, $events[0]);
        static::assertSame($criteria, $events[0]->getCriteria());
        static::assertSame($context, $events[0]->getSalesChannelContext());
    }
}
