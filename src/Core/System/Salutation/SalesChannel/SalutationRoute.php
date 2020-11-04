<?php declare(strict_types=1);

namespace Shopware\Core\System\Salutation\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
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

    /**
     * @var RequestCriteriaBuilder
     */
    private $requestCriteriaBuilder;

    /**
     * @var EntityDefinition
     */
    private $definition;

    public function __construct(
        SalesChannelRepositoryInterface $salesChannelRepository,
        RequestCriteriaBuilder $requestCriteriaBuilder,
        EntityDefinition $definition
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->requestCriteriaBuilder = $requestCriteriaBuilder;
        $this->definition = $definition;
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
     *      summary="Salutations",
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
    public function load(Request $request, SalesChannelContext $context, ?Criteria $criteria = null): SalutationRouteResponse
    {
        // @deprecated tag:v6.4.0 - Criteria will be required
        if (!$criteria) {
            $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->definition, $context->getContext());
        }

        return new SalutationRouteResponse($this->salesChannelRepository->search($criteria, $context)->getEntities());
    }
}
