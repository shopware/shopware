<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
class ShippingMethodRoute extends AbstractShippingMethodRoute
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

    public function getDecorated(): AbstractShippingMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("shipping_method")
     * @OA\Post(
     *      path="/shipping-method",
     *      summary="Loads all available shipping methods",
     *      operationId="readShippingMethod",
     *      tags={"Store API", "Shipping Method"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="onlyAvailable", description="List only available", type="boolean")
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="All available shipping methods",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/shipping_method_flat"))
     *     )
     * )
     * @Route("/store-api/v{version}/shipping-method", name="store-api.shipping.method", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, ?Criteria $criteria = null): ShippingMethodRouteResponse
    {
        // @deprecated tag:v6.4.0 - Criteria will be required
        if (!$criteria) {
            $criteria = $this->criteriaBuilder->handleRequest($request, new Criteria(), $this->shippingMethodDefinition, $context->getContext());
        }

        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        /** @var ShippingMethodCollection $shippingMethods */
        $shippingMethods = $this->shippingMethodRepository->search($criteria, $context)->getEntities();

        if ($request->query->getBoolean('onlyAvailable', false)) {
            $shippingMethods = $shippingMethods->filterByActiveRules($context);
        }

        return new ShippingMethodRouteResponse($shippingMethods);
    }
}
