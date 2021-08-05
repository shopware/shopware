<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class SortedPaymentMethodRoute extends AbstractPaymentMethodRoute
{
    private AbstractPaymentMethodRoute $decorated;

    public function __construct(AbstractPaymentMethodRoute $decorated)
    {
        $this->decorated = $decorated;
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("payment_method")
     * @OA\Post (
     *      path="/payment-method",
     *      summary="Loads all available payment methods",
     *      operationId="readPaymentMethod",
     *      tags={"Store API", "Payment Method"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="onlyAvailable", description="List only available", type="boolean")
     *          )
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
     *                  @OA\Items(ref="#/components/schemas/PaymentMethod")
     *              )
     *       )
     *    )
     * )
     * @Route("/store-api/payment-method", name="store-api.payment.method", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $response = $this->getDecorated()->load($request, $context, $criteria);

        $response->getPaymentMethods()->sortPaymentMethodsByPreference($context);

        return $response;
    }
}
