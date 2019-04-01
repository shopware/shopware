<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Controller;

use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
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
     * @Route("/payment/finalize-transaction", name="payment.finalize.transaction", methods={"GET"})
     *
     * @throws AsyncPaymentFinalizeException
     * @throws CustomerCanceledAsyncPaymentException
     * @throws InvalidTransactionException
     * @throws TokenExpiredException
     * @throws UnknownPaymentMethodException
     */
    public function finalizeTransaction(Request $request, Context $context): Response
    {
        $paymentToken = $request->get('_sw_payment_token');

        $paymentTokenStruct = $this->paymentService->finalizeTransaction($paymentToken, $request, $context);

        if ($paymentTokenStruct->getFinishUrl()) {
            return new RedirectResponse($paymentTokenStruct->getFinishUrl());
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
