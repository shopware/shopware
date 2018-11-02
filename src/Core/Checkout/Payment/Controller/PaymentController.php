<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Controller;

use Doctrine\DBAL\Exception\InvalidArgumentException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTokenException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    /**
     * @var PaymentService
     */
    private $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @Route("/payment/finalize-transaction", name="payment.finalize.transaction")
     *
     * @throws UnknownPaymentMethodException
     * @throws InvalidArgumentException
     * @throws InvalidTokenException
     * @throws TokenExpiredException
     * @throws CustomerNotLoggedInException
     */
    public function finalizeTransaction(Request $request, Context $context): Response
    {
        $paymentToken = $request->get('_sw_payment_token');
        $finishUrl = $request->get('_sw_finish_url');

        $this->paymentService->finalizeTransaction($paymentToken, $request, $context);

        if ($finishUrl) {
            return new RedirectResponse($finishUrl);
        }

        // todo for transaction support -> check if order is completed, if not redirect to pay action

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
