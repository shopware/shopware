<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class SortedShippingMethodRoute extends AbstractShippingMethodRoute
{
    private AbstractShippingMethodRoute $decorated;

    /**
     * @internal
     */
    public function __construct(AbstractShippingMethodRoute $decorated)
    {
        $this->decorated = $decorated;
    }

    public function getDecorated(): AbstractShippingMethodRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("shipping_method")
     * @OA\Post(
     *      path="/shipping-method",
     *      summary="Fetch shipping methods",
     *      description="Perform a filtered search for shipping methods.",
     *      operationId="readShippingMethod",
     *      tags={"Store API", "Payment & Shipping"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          name="onlyAvailable",
     *          description="List only available shipping methods. This filters shipping methods methods which can not be used in the actual context because of their availability rule.",
     *          @OA\Schema(type="boolean"),
     *          in="query"
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="",
     *          @OA\JsonContent(type="object",
     *              @OA\Property(
     *                  property="total",
     *                  type="integer",
     *                  description="Total amount"
     *              ),
     *              @OA\Property(
     *                  property="aggregations",
     *                  type="object",
     *                  description="aggregation result"
     *              ),
     *              @OA\Property(
     *                  property="elements",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/ShippingMethod")
     *              )
     *          )
     *     )
     * )
     * @Route("/store-api/shipping-method", name="store-api.shipping.method", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $response = $this->getDecorated()->load($request, $context, $criteria);

        $response->getShippingMethods()->sortShippingMethodsByPreference($context);

        return $response;
    }
}
