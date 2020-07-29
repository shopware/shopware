<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Listing;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AddressListingPageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $countryRepository;

    /**
     * @var SalesChannelRepositoryInterface
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
        GenericPageLoaderInterface $genericLoader,
        SalesChannelRepositoryInterface $countryRepository,
        SalesChannelRepositoryInterface $salutationRepository,
        EntityRepositoryInterface $addressRepository,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService
    ) {
        $this->genericLoader = $genericLoader;
        $this->countryRepository = $countryRepository;
        $this->salutationRepository = $salutationRepository;
        $this->addressRepository = $addressRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AddressListingPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AddressListingPage::createFrom($page);

        $page->setSalutations($this->getSalutations($salesChannelContext));

        $page->setCountries($this->getCountries($salesChannelContext));

        $page->setAddresses($this->getAddresses($salesChannelContext));

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
    private function getSalutations(SalesChannelContext $salesChannelContext): SalutationCollection
    {
        /** @var SalutationCollection $salutations */
        $salutations = $this->salutationRepository->search(new Criteria(), $salesChannelContext)->getEntities();

        $salutations->sort(function (SalutationEntity $a, SalutationEntity $b) {
            return $b->getSalutationKey() <=> $a->getSalutationKey();
        });

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

        /** @var CountryCollection $countries */
        $countries = $this->countryRepository
            ->search($criteria, $salesChannelContext)
            ->getEntities();

        $countries->sortCountryAndStates();

        return $countries;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     */
    private function getAddresses(SalesChannelContext $context): CustomerAddressCollection
    {
        if (!$context->getCustomer()) {
            throw new CustomerNotLoggedInException();
        }

        $criteria = (new Criteria())
            ->addAssociation('country')
            ->addFilter(new EqualsFilter('customer_address.customerId', $context->getCustomer()->getId()));

        $this->eventDispatcher->dispatch(
            new AddressListingCriteriaEvent($criteria, $context)
        );

        /** @var CustomerAddressCollection $collection */
        $collection = $this->addressRepository->search($criteria, $context->getContext())->getEntities();

        return $collection;
    }
}
