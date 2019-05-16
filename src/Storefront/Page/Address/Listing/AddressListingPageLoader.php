<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Listing;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AddressListingPageLoader
{
    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    /**
     * @var EntityRepositoryInterface
     */
    private $countryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salutationRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(
        GenericPageLoader $genericLoader,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $salutationRepository,
        EntityRepositoryInterface $addressRepository,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService
    ) {
        $this->genericLoader = $genericLoader;
        $this->countryRepository = $countryRepository;
        $this->salutationRepository = $salutationRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->addressRepository = $addressRepository;
        $this->cartService = $cartService;
    }

    public function load(Request $request, SalesChannelContext $context): AddressListingPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = AddressListingPage::createFrom($page);

        $page->setSalutations($this->getSalutations($context));

        $page->setCountries($this->getCountries($context));

        $page->setAddresses($this->getAddresses($context));

        $page->setCart($this->cartService->getCart($context->getToken(), $context));

        $page->setAddress(
            $page->getAddresses()->get($request->get('addressId'))
        );

        $this->eventDispatcher->dispatch(
            AddressListingPageLoadedEvent::NAME,
            new AddressListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function getSalutations(SalesChannelContext $context): SalutationCollection
    {
        $criteria = new Criteria([]);
        $criteria->addSorting(new FieldSorting('salutationKey', 'DESC'));

        /** @var SalutationCollection $collection */
        $collection = $this->salutationRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $collection;
    }

    private function getCountries(SalesChannelContext $context): CountryCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('country.active', true))
            ->addAssociation('country.states');

        /** @var CountryCollection $countries */
        $countries = $this->countryRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        $countries->sortCountryAndStates();

        return $countries;
    }

    private function getAddresses(SalesChannelContext $context): CustomerAddressCollection
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer_address.customerId', $context->getCustomer()->getId()));

        /** @var CustomerAddressCollection $collection */
        $collection = $this->addressRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $collection;
    }
}
