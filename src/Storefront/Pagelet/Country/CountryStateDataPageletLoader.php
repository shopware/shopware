<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Country;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryStateRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @package storefront
 */
class CountryStateDataPageletLoader
{
    private EventDispatcherInterface $eventDispatcher;

    private AbstractCountryStateRoute $countryStateRoute;

    /**
     * @internal
     */
    public function __construct(AbstractCountryStateRoute $countryStateRoute, EventDispatcherInterface $eventDispatcher)
    {
        $this->countryStateRoute = $countryStateRoute;
        $this->eventDispatcher = $eventDispatcher;
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
