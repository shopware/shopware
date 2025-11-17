<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Controller;

use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentToken;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenGenerator;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\JWT\JWTException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\ShopwareException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
class PaymentController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly PaymentProcessor $paymentProcessor,
        private readonly OrderConverter $orderConverter,
        private readonly TokenFactoryInterfaceV2 $tokenFactory,
        private readonly PaymentTokenGenerator $paymentTokenGenerator,
        private readonly PaymentTokenLifecycle $paymentTokenLifecycle,
        private readonly EntityRepository $orderRepository
    ) {
    }

    /**
     * The route scope could not be defined as this route is called from external.
     * An API route scope would normally imply an authentication, which external callers could not provide.
     * Only a storefront route scope could also not be used, as it also needs to work on headless environments.
     *
     * @phpstan-ignore shopware.routeScope
     */
    #[Route(
        path: '/payment/finalize-transaction',
        name: 'payment.finalize.transaction',
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function finalizeTransaction(Request $request): Response
    {
        $paymentToken = $request->get('_sw_payment_token');

        if (!\is_string($paymentToken)) {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                throw RoutingException::missingRequestParameter('_sw_payment_token'); // @phpstan-ignore-line shopware.domainException
            }
            throw PaymentException::missingRequestParameter('_sw_payment_token');
        }

        try {
            $token = null;
            $oldToken = null;
            if (Feature::isActive('v6.8.0.0')) {
                $token = $this->paymentTokenGenerator->decode($paymentToken);
            }

            $return = null;
            Feature::callSilentIfInactive('v6.8.0.0', function () use ($paymentToken, &$token, &$oldToken, &$return): void {
                $oldToken = $this->tokenFactory->parseToken($paymentToken);

                $token = new PaymentToken();
                $token->jti = $paymentToken;
                $token->finishUrl = $oldToken->getFinishUrl();
                $token->errorUrl = $oldToken->getErrorUrl();

                if (!$oldToken->getTransactionId() || !$oldToken->getPaymentMethodId()) {
                    $this->invalidate($token->jti);

                    $return = $this->handleError(PaymentException::invalidToken($paymentToken), $token);

                    return;
                }

                $token->paymentMethodId = $oldToken->getPaymentMethodId();
                $token->transactionId = $oldToken->getTransactionId();

                if ($oldToken->isExpired()) {
                    $this->invalidate($token->jti);

                    $return = $this->handleError(PaymentException::tokenExpired($paymentToken), $token);
                }
            });

            if ($return !== null) {
                return $return;
            }

            \assert($token instanceof PaymentToken);
        } catch (JWTException $e) {
            try {
                // try to decode without validation for graceful error handling
                $token = $this->paymentTokenGenerator->decode($paymentToken, true);
                $this->invalidate($token->jti);
            } catch (\Throwable $e) {
                throw PaymentException::invalidToken($paymentToken, $e);
            }

            return $this->handleError($e, $token);
        } catch (PaymentException $e) {
            // @deprecated tag:v6.8.0 - remove this catch block
            $this->invalidate($token?->jti);

            throw $e;
        } catch (\Throwable $e) {
            $this->invalidate($token?->jti);

            throw PaymentException::invalidToken($paymentToken, $e);
        }

        if (Feature::isActive('REPEATED_PAYMENT_FINALIZE') && $token->jti !== null && !$this->paymentTokenLifecycle->isConsumable($token->jti)) {
            return $this->handleFinish($token);
        }

        $salesChannelContext = $this->assembleSalesChannelContext($token);

        try {
            $deprecatedParameter = $oldToken ?? null;
            Feature::silent('v6.8.0.0', function () use (&$deprecatedParameter): void {
                $deprecatedParameter ??= new TokenStruct();
            });
            \assert($deprecatedParameter instanceof TokenStruct);

            $this->paymentProcessor->finalize(
                $deprecatedParameter,
                $request,
                $salesChannelContext,
                $token,
            );

            Feature::callSilentIfInactive('v6.8.0.0', function () use ($deprecatedParameter): void {
                $exception = $deprecatedParameter->getException();
                if ($exception) {
                    throw $exception;
                }
            });

            return $this->handleFinish($token);
        } catch (\Throwable $e) {
            return $this->handleError($e, $token);
        } finally {
            if ($token->jti !== null) {
                $this->invalidate($token->jti);
            }
        }
    }

    private function handleError(\Throwable $exception, ?PaymentToken $token): Response
    {
        $errorUrl = $token?->errorUrl;
        if ($errorUrl === null) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        if ($exception instanceof ShopwareException) {
            $errorUrl .= (parse_url($errorUrl, \PHP_URL_QUERY) ? '&' : '?') . 'error-code=' . $exception->getErrorCode();
        }

        return new RedirectResponse($errorUrl);
    }

    private function handleFinish(PaymentToken $token): Response
    {
        if ($token->finishUrl) {
            return new RedirectResponse($token->finishUrl);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function assembleSalesChannelContext(PaymentToken $token): SalesChannelContext
    {
        $context = Context::createDefaultContext();

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('transactions.id', $token->transactionId))
            ->addAssociations(['transactions', 'orderCustomer']);

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();
        if (!$order) {
            throw PaymentException::invalidToken($token->jti ?? '');
        }

        return $this->orderConverter->assembleSalesChannelContext($order, $context);
    }

    /**
     * @deprecated tag:v6.8.0 - move code inline as it is easier to read now
     */
    private function invalidate(?string $token): void
    {
        if ($token === null) {
            return;
        }

        if (Feature::isActive('v6.8.0.0')) {
            $this->paymentTokenLifecycle->invalidateToken($token);
        }

        Feature::callSilentIfInactive('v6.8.0.0', function () use ($token): void {
            $this->tokenFactory->invalidateToken($token);
        });
    }
}
