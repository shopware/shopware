<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Controller;

use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
     * @RouteScope(scopes={"storefront"})
     * @Route("/payment/finalize-transaction", defaults={"auth_required"=false}, name="payment.finalize.transaction", methods={"GET", "POST"})
     *
     * @throws AsyncPaymentFinalizeException
     * @throws CustomerCanceledAsyncPaymentException
     * @throws InvalidTransactionException
     * @throws TokenExpiredException
     * @throws UnknownPaymentMethodException
     */
    public function finalizeTransaction(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $paymentToken = $request->get('_sw_payment_token');

        $result = $this->paymentService->finalizeTransaction(
            $paymentToken,
            $request,
            $salesChannelContext
        );

        $exception = $result->getException();

        if ($exception !== null) {
            $url = $result->getErrorUrl();

            if ($exception instanceof PaymentProcessException) {
                $url .= (parse_url((string) $url, PHP_URL_QUERY) ? '&' : '?') . 'error-code=' . $exception->getErrorCode();
            }

            return new RedirectResponse($url);
        }

        if ($result->getFinishUrl()) {
            return new RedirectResponse($result->getFinishUrl());
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
