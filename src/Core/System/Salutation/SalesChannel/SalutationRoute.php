<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
class SalutationRoute extends AbstractSalutationRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $salesChannelRepository;

    public function __construct(
        SalesChannelRepositoryInterface $salesChannelRepository
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function getDecorated(): AbstractSalutationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("salutation")
     * @OA\Post(
     *      path="/salutation",
     *      summary="Fetch salutations",
     *      description="Perform a filtered search for salutations.",
     *      operationId="readSalutation",
     *      tags={"Store API", "System & Context"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Entity search result containing salutations.",
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(ref="#/components/schemas/EntitySearchResult"),
     *                  @OA\Schema(type="object",
     *                      @OA\Property(
     *                          type="array",
     *                          property="elements",
     *                          @OA\Items(ref="#/components/schemas/Salutation")
     *                      )
     *                  )
     *              }
     *          )
     *     )
     * )
     * @Route(path="/store-api/salutation", name="store-api.salutation", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SalutationRouteResponse
    {
        return new SalutationRouteResponse($this->salesChannelRepository->search($criteria, $context));
    }
}
