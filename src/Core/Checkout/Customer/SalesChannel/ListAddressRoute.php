<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\AddressListingCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Address\Listing\AddressListingCriteriaEvent as StorefrontAddressListingCriteriaEvent;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ListAddressRoute extends AbstractListAddressRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(EntityRepositoryInterface $addressRepository, EventDispatcherInterface $eventDispatcher)
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
     * @OA\Post(
     *      path="/account/list-address",
     *      summary="Fetch addresses of a customer",
     *      description="Lists all addresses of the current customer and allows filtering them based on a criteria.",
     *      operationId="listAddress",
     *      tags={"Store API", "Address"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/CustomerAddress"))
     *     )
     * )
     * @LoginRequired(allowGuest=true)
     * @Route(path="/store-api/account/list-address", name="store-api.account.address.list.get", methods={"GET", "POST"})
     */
    public function load(Criteria $criteria, SalesChannelContext $context, CustomerEntity $customer): ListAddressRouteResponse
    {
        $criteria
            ->addAssociation('salutation')
            ->addAssociation('country')
            ->addAssociation('countryState')
            ->addFilter(new EqualsFilter('customer_address.customerId', $customer->getId()));

        if (\class_exists(StorefrontAddressListingCriteriaEvent::class)) {
            $this->eventDispatcher->dispatch(new StorefrontAddressListingCriteriaEvent($criteria, $context));
        }
        $this->eventDispatcher->dispatch(new AddressListingCriteriaEvent($criteria, $context));

        return new ListAddressRouteResponse($this->addressRepository->search($criteria, $context->getContext()));
    }
}
