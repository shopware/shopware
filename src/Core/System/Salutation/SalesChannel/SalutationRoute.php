<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    public function __construct(SalesChannelRepositoryInterface $salesChannelRepository, RequestCriteriaBuilder $requestCriteriaBuilder)
    {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
    }

    public function getDecorated(): AbstractSalutationRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/salutation",
     *      description="Salutations",
     *      operationId="readSalutation",
     *      tags={"Store API", "Salutation"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/salutation_flat"))
     *     )
     * )
     * @Route(path="/store-api/v{version}/salutation", name="store-api.salutation", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context): SalutationRouteResponse
    {
        $criteria = new Criteria();

        $criteria = $this->requestCriteriaBuilder->handleRequest(
            $request,
            $criteria,
            new SalesChannelSalutationDefinition(),
            $context->getContext()
        );

        return new SalutationRouteResponse($this->salesChannelRepository->search($criteria, $context)->getEntities());
    }
}
