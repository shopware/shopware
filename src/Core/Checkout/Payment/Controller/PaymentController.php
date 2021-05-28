<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Controller;

use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\ShopwareException;
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
     * @Since("6.0.0.0")
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

        if ($paymentToken === null) {
            throw new MissingRequestParameterException('_sw_payment_token');
        }

        $result = $this->paymentService->finalizeTransaction(
            $paymentToken,
            $request,
            $salesChannelContext
        );

        $response = $this->handleException($result);
        if ($response !== null) {
            return $response;
        }

        $finishUrl = $result->getFinishUrl();
        if ($finishUrl) {
            return new RedirectResponse($finishUrl);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function handleException(TokenStruct $token): ?Response
    {
        if ($token->getException() === null) {
            return null;
        }

        if ($token->getErrorUrl() === null) {
            return null;
        }

        $url = $token->getErrorUrl();

        $exception = $token->getException();

        if ($exception instanceof ShopwareException) {
            return new RedirectResponse(
                (parse_url($url, \PHP_URL_QUERY) ? '&' : '?') . 'error-code=' . $exception->getErrorCode()
            );
        }

        return new RedirectResponse($url);
    }
}
