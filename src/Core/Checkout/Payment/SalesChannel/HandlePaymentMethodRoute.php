<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @RouteScope(scopes={"store-api"})
 */
class HandlePaymentMethodRoute extends AbstractHandlePaymentMethodRoute
{
    /**
     * @var PaymentService
     */
    private $paymentService;

    /**
     * @var DataValidator
     */
    private $dataValidator;

    public function __construct(
        PaymentService $paymentService,
        DataValidator $dataValidator
    ) {
        $this->paymentService = $paymentService;
        $this->dataValidator = $dataValidator;
    }

    public function getDecorated(): AbstractHandlePaymentMethodRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/handle-payment",
     *      summary="Initiate a payment for an order",
     *      description="This generic endpoint is should be called to initiate a payment flow after an order has been created. The details of the payment flow can differ depending on the payment integration and might require calling additional operations or the setup of webhooks.

The endpoint internally calls the payment handler of the payment method currently set for the order.",
     *      operationId="handlePaymentMethod",
     *      tags={"Store API", "Payment & Shipping"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={
     *                  "orderId"
     *              },
     *              @OA\Property(
     *                  property="orderId",
     *                  type="string",
     *                  description="Identifier of an order"),
     *              @OA\Property(
     *                  property="finishUrl",
     *                  type="string",
     *                  description="URL to which the client should be redirected after successful payment"),
     *              @OA\Property(
     *                  property="errorUrl",
     *                  type="string",
     *                  description="URL to which the client should be redirected after erroneous payment")
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Redirect to external payment provider"
     *     )
     * )
     * @Route("/store-api/handle-payment", name="store-api.payment.handle", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context): HandlePaymentMethodRouteResponse
    {
        $data = array_merge($request->query->all(), $request->request->all());
        $this->dataValidator->validate($data, $this->createDataValidation());

        $response = $this->paymentService->handlePaymentByOrder(
            $request->get('orderId'),
            new RequestDataBag($request->request->all()),
            $context,
            $request->get('finishUrl'),
            $request->get('errorUrl')
        );

        return new HandlePaymentMethodRouteResponse($response);
    }

    private function createDataValidation(): DataValidationDefinition
    {
        return (new DataValidationDefinition())
            ->add('orderId', new NotBlank(), new Type('string'))
            ->add('finishUrl', new Type('string'))
            ->add('errorUrl', new Type('string'));
    }
}
