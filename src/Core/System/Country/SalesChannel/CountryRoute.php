<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CountryRoute extends AbstractCountryRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $countryRepository;

    public function __construct(
        SalesChannelRepositoryInterface $countryRepository
    ) {
        $this->countryRepository = $countryRepository;
    }

    /**
     * @Since("6.3.0.0")
     * @OA\Post(
     *      path="/country",
     *      summary="Loads all available countries",
     *      operationId="readCountry",
     *      tags={"Store API", "Country"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="onlyAvailable", description="Lists only available countries", type="integer")
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="All available countries",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/country_flat"))
     *     )
     * )
     * @Entity("country")
     * @Route("/store-api/country", name="store-api.country", methods={"GET", "POST"})
     */
    public function load(Request $request, Criteria $criteria, SalesChannelContext $context): CountryRouteResponse
    {
        $criteria->addFilter(new EqualsFilter('active', true));

        $result = $this->countryRepository->search($criteria, $context);

        return new CountryRouteResponse($result);
    }

    protected function getDecorated(): AbstractCountryRoute
    {
        throw new DecorationPatternException(self::class);
    }
}
