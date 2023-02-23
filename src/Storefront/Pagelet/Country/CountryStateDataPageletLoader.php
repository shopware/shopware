<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Country;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryStateRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('storefront')]
class CountryStateDataPageletLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCountryStateRoute $countryStateRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(string $countryId, Request $request, SalesChannelContext $context): CountryStateDataPagelet
    {
        $page = new CountryStateDataPagelet();

        $criteria = new Criteria();

        $this->eventDispatcher->dispatch(new CountryStateDataPageletCriteriaEvent($criteria, $context, $request));

        $countryRouteResponse = $this->countryStateRoute->load($countryId, $request, $criteria, $context);

        $page->setStates($countryRouteResponse->getStates());

        $this->eventDispatcher->dispatch(new CountryStateDataPageletLoadedEvent($page, $context, $request));

        return $page;
    }
}
