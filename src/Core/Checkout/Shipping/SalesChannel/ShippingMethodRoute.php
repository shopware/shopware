<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ShippingMethodRoute implements ShippingMethodRouteInterface
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $shippingMethodRepository;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var SalesChannelShippingMethodDefinition
     */
    private $shippingMethodDefinition;

    public function __construct(
        SalesChannelRepositoryInterface $shippingMethodRepository,
        RequestCriteriaBuilder $criteriaBuilder,
        SalesChannelShippingMethodDefinition $shippingMethodDefinition
    ) {
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->shippingMethodDefinition = $shippingMethodDefinition;
    }

    /**
     * @OA\Get(
     *      path="/shipping-method",
     *      description="Loads all available shipping methods",
     *      operationId="readShippingMethod",
     *      tags={"Store API", "Shipping Method"},
     *      @OA\Parameter(
     *          parameter="limit",
     *          name="limit",
     *          in="query",
     *          description="Limit",
     *          @OA\Schema(type="integer"),
     *      ),
     *      @OA\Parameter(
     *          parameter="offset",
     *          name="offset",
     *          in="query",
     *          description="Offset",
     *          @OA\Schema(type="integer"),
     *      ),
     *      @OA\Parameter(
     *          parameter="term",
     *          name="term",
     *          in="query",
     *          description="The term to search for",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          parameter="filter",
     *          name="filter",
     *          in="query",
     *          description="Encoded SwagQL in JSON",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          parameter="aggregations",
     *          name="aggregations",
     *          in="query",
     *          description="Encoded SwagQL in JSON",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          parameter="associations",
     *          name="associations",
     *          in="query",
     *          description="Encoded SwagQL in JSON",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="All available shipping methods",
     *          @OA\JsonContent(ref="#/components/schemas/payment_method_flat")
     *     )
     * )
     * @Route("/store-api/v{version}/shipping-method", name="shop-api.shipping.method", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context): ShippingMethodRouteResponse
    {
        $shippingMethodsCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        $shippingMethodsCriteria = $this->criteriaBuilder->handleRequest(
            $request,
            $shippingMethodsCriteria,
            $this->shippingMethodDefinition,
            $context->getContext()
        );

        /** @var ShippingMethodCollection $shippingMethods */
        $shippingMethods = $this->shippingMethodRepository->search($shippingMethodsCriteria, $context)->getEntities();

        return new ShippingMethodRouteResponse($shippingMethods);
    }
}
