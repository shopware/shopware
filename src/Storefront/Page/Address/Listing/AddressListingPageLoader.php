<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Listing;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class AddressListingPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractCountryRoute $countryRoute,
        private readonly AbstractSalutationRoute $salutationRoute,
        private readonly AbstractListAddressRoute $listAddressRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartService $cartService
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer): AddressListingPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AddressListingPage::createFrom($page);

        $page->setSalutations($this->getSalutations($salesChannelContext));

        $page->setCountries($this->getCountries($salesChannelContext));

        $criteria = (new Criteria())->addSorting(new FieldSorting('firstName', FieldSorting::ASCENDING));

        $page->setAddresses($this->listAddressRoute->load($criteria, $salesChannelContext, $customer)->getAddressCollection());

        $page->setCart($this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext));

        $page->setAddress(
            $page->getAddresses()->get($request->get('addressId'))
        );

        $this->eventDispatcher->dispatch(
            new AddressListingPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getSalutations(SalesChannelContext $context): SalutationCollection
    {
        $salutations = $this->salutationRoute->load(new Request(), $context, new Criteria())->getSalutations();

        $salutations->sort(fn (SalutationEntity $a, SalutationEntity $b) => $b->getSalutationKey() <=> $a->getSalutationKey());

        return $salutations;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getCountries(SalesChannelContext $salesChannelContext): CountryCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('country.active', true))
            ->addAssociation('states');

        $countries = $this->countryRoute
            ->load(new Request(), $criteria, $salesChannelContext)
            ->getCountries();

        $countries->sortCountryAndStates();

        return $countries;
    }
}
