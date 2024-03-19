<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\Adapter\Cache\Event\AddCacheTagEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('buyers-experience')]
class CountryStateRoute extends AbstractCountryStateRoute
{
    final public const ALL_TAG = 'country-state-route';

    /**
     * @internal
     *
     * @param EntityRepository<CountryStateCollection> $countryStateRepository
     */
    public function __construct(
        private readonly EntityRepository $countryStateRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'country-state-route-' . $id;
    }

    #[Route(path: '/store-api/country-state/{countryId}', name: 'store-api.country.state', methods: ['GET', 'POST'], defaults: ['_entity' => 'country'])]
    public function load(string $countryId, Request $request, Criteria $criteria, SalesChannelContext $context): CountryStateRouteResponse
    {
        $this->dispatcher->dispatch(new AddCacheTagEvent(
            self::buildName($countryId),
            self::ALL_TAG
        ));

        $criteria->addFilter(
            new EqualsFilter('countryId', $countryId),
            new EqualsFilter('active', true)
        );
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING, true));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $countryStates = $this->countryStateRepository->search($criteria, $context->getContext());

        return new CountryStateRouteResponse($countryStates);
    }

    protected function getDecorated(): AbstractCountryStateRoute
    {
        throw new DecorationPatternException(self::class);
    }
}
