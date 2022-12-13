<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\AddressListingCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package customer-order
 *
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class ListAddressRoute extends AbstractListAddressRoute
{
    private EntityRepository $addressRepository;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @internal
     */
    public function __construct(EntityRepository $addressRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->addressRepository = $addressRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractListAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.2.0")
     * @Entity("customer_address")
     * @Route(path="/store-api/account/list-address", name="store-api.account.address.list.get", methods={"GET", "POST"}, defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     */
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
