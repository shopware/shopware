<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class OrderStateChangeRoute extends AbstractOrderStateChangeRoute
{
    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function getDecorated(): AbstractOrderStateChangeRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/order/state/transition",
     *      description="Change the state of an order",
     *      operationId="changeOrderState",
     *      tags={"Store API", "Order"},
     *      @OA\Parameter(
     *          name="orderId",
     *          in="post",
     *          required=true,
     *          description="The id of the order to be changed",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="transition",
     *          in="post",
     *          required=true,
     *          description="The transition the OrderState should execute",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          @OA\JsonContent(ref="#/components/schemas/state_machine_state_flat")
     *     )
     * )
     * @Route(path="/store-api/v{version}/order/state/transition", name="store-api.order.state.transition", methods={"POST"})
     */
    public function change(Request $request, SalesChannelContext $context): OrderStateChangeRouteResponse
    {
        if ($context->getCustomer() === null) {
            throw new CustomerNotLoggedInException();
        }
        $newState = $this->orderService->orderStateTransition(
            $request->get('orderId'),
            'cancel',
            new ParameterBag(),
            $context->getContext(),
            $context->getCustomer()->getId()
        );

        return new OrderStateChangeRouteResponse($newState);
    }
}
