<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;

/**
 * @RouteScope(scopes={"store-api"})
 */
class AccountCancelOrderRoute extends AbstractAccountCancelOrderRoute
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

    public function getDecorated(): AbstractAccountCancelOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Post(
     *      path="/account/order/cancel",
     *      description="Cancel an order",
     *      operationId="cancelOrder",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          name="orderId",
     *          in="post",
     *          required=true,
     *          description="The id of the order to be cancelled",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          @OA\JsonContent(ref="#/components/schemas/state_machine_state_flat")
     *     )
     * )
     * @Route(path="/store-api/v{version}/account/order/cancel", name="store-api.account.order", methods={"POST"})
     */
    public function load(Request $request, SalesChannelContext $context): AccountCancelOrderRouteResponse
    {
        $newState = $this->orderService->orderStateTransition($request->get('orderId'), 'cancel', 1, new DataBag(), $context->getContext());

        $newState = new ArrayEntity($newState);

        return new AccountCancelOrderRouteResponse($newState);
    }
}