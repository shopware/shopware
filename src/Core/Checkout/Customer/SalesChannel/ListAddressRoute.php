<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\AddressListingCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('customer-order')]
class ListAddressRoute extends AbstractListAddressRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $addressRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractListAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/list-address', name: 'store-api.account.address.list.get', methods: ['GET', 'POST'], defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true, '_entity' => 'customer_address'])]
    public function load(Criteria $criteria, SalesChannelContext $context, CustomerEntity $customer): ListAddressRouteResponse
    {
        $criteria
            ->addAssociation('salutation')
            ->addAssociation('country')
            ->addAssociation('countryState')
            ->addFilter(new EqualsFilter('customer_address.customerId', $customer->getId()));

        $this->eventDispatcher->dispatch(new AddressListingCriteriaEvent($criteria, $context));

        return new ListAddressRouteResponse($this->addressRepository->search($criteria, $context->getContext()));
    }
}
