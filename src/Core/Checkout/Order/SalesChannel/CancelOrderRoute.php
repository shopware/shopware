<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CancelOrderRoute extends AbstractCancelOrderRoute
{
    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function getDecorated(): AbstractCancelOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/order/state/cancel",
     *      summary="Cancel an order",
     *      description="Cancels an order. The order state will be set to 'cancelled'.",
     *      operationId="cancelOrder",
     *      tags={"Store API", "Order"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="orderId",
     *                  description="The identifier of the order to be canceled.",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns the state of the state machine

example: More information about the state machine can be found in the corresponding guide: [Using the state machine](https://developer.shopware.com/docs/guides/plugins/plugins/checkout/order/using-the-state-machine)",
     *          @OA\JsonContent(ref="#/components/schemas/state_machine_state_flat")
     *     )
     * )
     * @LoginRequired(allowGuest=true)
     * @Route(path="/store-api/order/state/cancel", name="store-api.order.state.cancel", methods={"POST"})
     */
    public function cancel(Request $request, SalesChannelContext $context): CancelOrderRouteResponse
    {
        $newState = $this->orderService->orderStateTransition(
            $request->get('orderId'),
            'cancel',
            new ParameterBag(),
            $context->getContext()
        );

        return new CancelOrderRouteResponse($newState);
    }
}
