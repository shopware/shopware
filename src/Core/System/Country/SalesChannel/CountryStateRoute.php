<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class CountryStateRoute extends AbstractCountryStateRoute
{
    private EntityRepository $countryStateRepository;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $countryStateRepository
    ) {
        $this->countryStateRepository = $countryStateRepository;
    }

    /**
     * @Since("6.4.14.0")
     * @OA\Post(
     *      path="/country-state",
     *      summary="Fetch the states of a country",
     *      description="Perform a filtered search the states for a country",
     *      operationId="readCountryState",
     *      tags={"Store API", "System & Context"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Entity search result containing countries.",
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(ref="#/components/schemas/EntitySearchResult"),
     *                  @OA\Schema(type="object",
     *                      @OA\Property(
     *                          type="array",
     *                          property="elements",
     *                          @OA\Items(ref="#/components/schemas/CountryState")
     *                      )
     *                  )
     *              }
     *          )
     *     )
     * )
     * @Entity("country")
     * @Route("/store-api/country-state/{countryId}", name="store-api.country.state", methods={"GET", "POST"})
     */
    public function load(string $countryId, Request $request, Criteria $criteria, SalesChannelContext $context): CountryStateRouteResponse
    {
        $criteria->addFilter(
            new EqualsFilter('countryId', $countryId),
            new EqualsFilter('active', true)
        );
        $criteria->addSorting(new FieldSorting('position', FieldSorting::ASCENDING, true));

        $countryStates = $this->countryStateRepository->search($criteria, $context->getContext());

        return new CountryStateRouteResponse($countryStates);
    }

    protected function getDecorated(): AbstractCountryStateRoute
    {
        throw new DecorationPatternException(self::class);
    }
}
