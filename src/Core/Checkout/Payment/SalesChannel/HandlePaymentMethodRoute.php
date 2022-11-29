<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

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
 * @package checkout
 *
 * @Route(defaults={"_routeScope"={"store-api"}})
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

    /**
     * @internal
     */
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
