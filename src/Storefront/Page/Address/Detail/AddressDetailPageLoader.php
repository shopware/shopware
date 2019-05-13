<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Detail;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
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

class AddressDetailPageLoader
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
     * @var AddressService
     */
    private $addressService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        GenericPageLoader $genericLoader,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $salutationRepository,
        AddressService $addressService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->genericLoader = $genericLoader;
        $this->countryRepository = $countryRepository;
        $this->salutationRepository = $salutationRepository;
        $this->addressService = $addressService;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(Request $request, SalesChannelContext $context): AddressDetailPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = AddressDetailPage::createFrom($page);

        $page->setSalutations($this->getSalutations($context));

        $page->setCountries($this->getCountries($context));

        $page->setAddress($this->getAddress($request, $context));

        $this->eventDispatcher->dispatch(
            new AddressDetailPageLoadedEvent($page, $context, $request),
            AddressDetailPageLoadedEvent::NAME
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
            ->addAssociation('states');

        /** @var CountryCollection $countries */
        $countries = $this->countryRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        $countries->sortCountryAndStates();

        return $countries;
    }

    private function getAddress(Request $request, SalesChannelContext $context): ?CustomerAddressEntity
    {
        if (!$request->get('addressId')) {
            return null;
        }

        return $this->addressService->getById($request->get('addressId'), $context);
    }
}
