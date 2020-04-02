<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class SetPaymentOrderRoute extends AbstractSetPaymentOrderRoute
{
    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct(
        OrderService $orderService
    ) {
        $this->orderService = $orderService;
    }

    public function getDecorated(): AbstractSetPaymentOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/order/set-payment",
     *      description="set payment for an order",
     *      operationId="orderSetPayment",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(
     *          name="paymentMethodId",
     *          in="post",
     *          required=true,
     *          description="The id of the paymentMethod to be set",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="orderId",
     *          in="post",
     *          required=true,
     *          description="The id of the order",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response="200"
     *     )
     * )
     * @Route(path="/store-api/v{version}/order/payment", name="store-api.order.set-payment", methods={"POST"})
     */
    public function setPayment(Request $request, SalesChannelContext $salesChannelContext): SetPaymentOrderRouteResponse
    {
        $this->orderService->setPaymentMethod($request->get('paymentMethodId'), $request->get('orderId'), $salesChannelContext);

        return new SetPaymentOrderRouteResponse(new ArrayStruct(['success' => true]));
    }
}
