<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class HandlePaymentMethodRoute extends AbstractHandlePaymentMethodRoute
{
    /**
     * @var PaymentService
     */
    private $paymentService;

    public function __construct(
        PaymentService $paymentService
    ) {
        $this->paymentService = $paymentService;
    }

    public function getDecorated(): AbstractHandlePaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Get(
     *      path="/handle-payment",
     *      description="Handles a payment for an order",
     *      operationId="handlePaymentMethod",
     *      tags={"Store API", "Payment Method"},
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
     *          response="200",
     *          description="All available payment methods",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/payment_method_flat"))
     *     )
     * )
     * @Route("/store-api/v{version}/handle-payment", name="store-api.payment.handle", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context): HandlePaymentMethodRouteResponse
    {
        $response = $this->paymentService->handlePaymentByOrder(
            $request->get('orderId'),
            new RequestDataBag($request->request->all()),
            $context,
            $request->get('finishUrl'),
            $request->get('errorUrl')
        );

        return new HandlePaymentMethodRouteResponse($response);
    }
}
