<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('system-settings')]
class CountryStateRoute extends AbstractCountryStateRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $countryStateRepository)
    {
    }

    #[Route(path: '/store-api/country-state/{countryId}', name: 'store-api.country.state', methods: ['GET', 'POST'], defaults: ['_entity' => 'country'])]
    public function load(string $countryId, Request $request, Criteria $criteria, SalesChannelContext $context): CountryStateRouteResponse
    {
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
