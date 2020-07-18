<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
     *      @OA\Parameter(
     *          name="finishUrl",
     *          in="post",
     *          required=false,
     *          description="URL to which the external payment provider should redirect after successful payment",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="errorUrl",
     *          in="post",
     *          required=false,
     *          description="URL to which the external payment provider should redirect after erroneous payment",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Redirect to external payment provider"
     *     )
     * )
     * @Route("/store-api/v{version}/handle-payment", name="store-api.payment.handle", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context): HandlePaymentMethodRouteResponse
    {
        $data = \array_merge($request->query->all(), $request->request->all());
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
