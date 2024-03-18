<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Detail;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\AbstractSalutationsSorter;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('storefront')]
class AddressDetailPageLoader
{
    /**
     * @internal
     *
     * @deprecated tag:v6.7.0 - translator will be mandatory from 6.7
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractCountryRoute $countryRoute,
        private readonly AbstractSalutationRoute $salutationRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractListAddressRoute $listAddressRoute,
        private readonly AbstractSalutationsSorter $salutationsSorter,
        private readonly ?AbstractTranslator $translator = null
    ) {
    }

    /**
     * @throws AddressNotFoundException
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidUuidException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext, CustomerEntity $customer): AddressDetailPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = AddressDetailPage::createFrom($page);
        $this->setMetaInformation($page, $request);

        $page->setSalutations($this->getSalutations($salesChannelContext));

        $page->setCountries($this->getCountries($salesChannelContext));

        $page->setAddress($this->getAddress($request, $salesChannelContext, $customer));

        $this->eventDispatcher->dispatch(
            new AddressDetailPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AddressDetailPage $page, Request $request): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($this->translator !== null && $page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        if ($this->translator !== null && $request->attributes->get('_route') === 'frontend.account.address.create.page') {
            $page->getMetaInformation()?->setMetaTitle(
                $this->translator->trans('account.addressCreateMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
            );
        } elseif ($this->translator !== null && $request->attributes->get('_route') === 'frontend.account.address.edit.page') {
            $page->getMetaInformation()?->setMetaTitle(
                $this->translator->trans('account.addressEditMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
            );
        }
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getSalutations(SalesChannelContext $salesChannelContext): SalutationCollection
    {
        $salutations = $this->salutationRoute->load(new Request(), $salesChannelContext, new Criteria())->getSalutations();

        return $this->salutationsSorter->sort($salutations);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getCountries(SalesChannelContext $salesChannelContext): CountryCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('country.active', true))
            ->addAssociation('states');

        $countries = $this->countryRoute->load(new Request(), $criteria, $salesChannelContext)->getCountries();

        $countries->sortCountryAndStates();

        return $countries;
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidUuidException
     */
    private function getAddress(Request $request, SalesChannelContext $context, CustomerEntity $customer): ?CustomerAddressEntity
    {
        if (!$request->get('addressId')) {
            return null;
        }
        $addressId = $request->get('addressId');

        if (!Uuid::isValid($addressId)) {
            throw new InvalidUuidException($addressId);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));

        $address = $this->listAddressRoute->load($criteria, $context, $customer)->getAddressCollection()->get($addressId);

        if (!$address) {
            throw CustomerException::addressNotFound($addressId);
        }

        return $address;
    }
}
