<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Register;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
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

class CheckoutRegisterPageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var AddressService
     */
    private $addressService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var AbstractSalutationRoute
     */
    private $salutationRoute;

    /**
     * @var AbstractCountryRoute
     */
    private $countryRoute;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        AddressService $addressService,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        AbstractSalutationRoute $salutationRoute,
        AbstractCountryRoute $countryRoute
    ) {
        $this->genericLoader = $genericLoader;
        $this->addressService = $addressService;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->salutationRoute = $salutationRoute;
        $this->countryRoute = $countryRoute;
    }

    /**
     * @throws AddressNotFoundException
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidUuidException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): CheckoutRegisterPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = CheckoutRegisterPage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $page->setCountries($this->getCountries($salesChannelContext));
        $page->setCart($this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext));
        $page->setSalutations($this->getSalutations($salesChannelContext));

        $addressId = $request->attributes->get('addressId');
        if ($addressId) {
            $address = $this->addressService->getById((string) $addressId, $salesChannelContext);
            $page->setAddress($address);
        }

        $this->eventDispatcher->dispatch(
            new CheckoutRegisterPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getSalutations(SalesChannelContext $salesChannelContext): SalutationCollection
    {
        $salutations = $this->salutationRoute->load(new Request(), $salesChannelContext, new Criteria())->getSalutations();

        $salutations->sort(function (SalutationEntity $a, SalutationEntity $b) {
            return $b->getSalutationKey() <=> $a->getSalutationKey();
        });

        return $salutations;
    }

    private function getCountries(SalesChannelContext $salesChannelContext): CountryCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('country.states');

        $countries = $this->countryRoute->load($criteria, $salesChannelContext)->getCountries();

        $countries->sortCountryAndStates();

        return $countries;
    }
}
