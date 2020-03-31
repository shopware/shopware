<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
     * @OA\Get(
     *      path="/shipping-method",
     *      description="Loads all available shipping methods",
     *      operationId="readShippingMethod",
     *      tags={"Store API", "Shipping Method"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          parameter="onlyAvailable",
     *          name="onlyAvailable",
     *          in="query",
     *          description="Encoded SwagQL in JSON",
     *          @OA\Schema(type="int")
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="All available shipping methods",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/shipping_method_flat"))
     *     )
     * )
     * @Route("/store-api/v{version}/shipping-method", name="store-api.shipping.method", methods={"GET", "POST"})
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

        if ($request->query->getBoolean('onlyAvailable', false)) {
            $shippingMethods = $shippingMethods->filterByActiveRules($context);
        }

        return new ShippingMethodRouteResponse($shippingMethods);
    }
}
