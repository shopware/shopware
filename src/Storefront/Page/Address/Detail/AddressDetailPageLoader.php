<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Detail;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AddressDetailPageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var AbstractCountryRoute
     */
    private $countryRoute;

    /**
     * @var AbstractSalutationRoute
     */
    private $salutationRoute;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        AbstractCountryRoute $countryRoute,
        AbstractSalutationRoute $salutationRoute,
        AddressService $addressService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->genericLoader = $genericLoader;
        $this->countryRoute = $countryRoute;
        $this->salutationRoute = $salutationRoute;
        $this->addressService = $addressService;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws AddressNotFoundException
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidUuidException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): AddressDetailPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AddressDetailPage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $page->setSalutations($this->getSalutations($salesChannelContext));

        $page->setCountries($this->getCountries($salesChannelContext));

        $page->setAddress($this->getAddress($request, $salesChannelContext));

        $this->eventDispatcher->dispatch(
            new AddressDetailPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getSalutations(SalesChannelContext $salesChannelContext): SalutationCollection
    {
        $salutations = $this->salutationRoute->load(new Request(), $salesChannelContext)->getSalutations();

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

        $countries = $this->countryRoute->load($criteria, $salesChannelContext)->getCountries();

        $countries->sortCountryAndStates();

        return $countries;
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidUuidException
     */
    private function getAddress(Request $request, SalesChannelContext $salesChannelContext): ?CustomerAddressEntity
    {
        if (!$request->get('addressId')) {
            return null;
        }

        return $this->addressService->getById($request->get('addressId'), $salesChannelContext);
    }
}
