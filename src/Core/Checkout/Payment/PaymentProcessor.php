<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\AbstractPaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentToken;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenGenerator;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-final - will be final (with @final, not actual final, for testing purposes)
 */
#[Package('checkout')]
class PaymentProcessor
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     *
     * @internal
     */
    public function __construct(
        private readonly ?TokenFactoryInterfaceV2 $tokenFactory,
        private readonly PaymentTokenGenerator $paymentTokenGenerator,
        private readonly PaymentTokenLifecycle $paymentTokenLifecycle,
        private readonly PaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly EntityRepository $orderTransactionRepository,
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly LoggerInterface $logger,
        private readonly AbstractPaymentTransactionStructFactory $paymentTransactionStructFactory,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly RouterInterface $router,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function pay(
        string $orderId,
        Request $request,
        SalesChannelContext $salesChannelContext,
        ?string $finishUrl = null,
        ?string $errorUrl = null,
    ): ?RedirectResponse {
        $transaction = $this->getCurrentOrderTransaction($orderId, $salesChannelContext->getContext());
        if (!$transaction) {
            return null;
        }

        if (Feature::isActive('v6.8.0.0') || $this->tokenFactory === null) {
            $token = $this->getToken($transaction, $finishUrl, $errorUrl, $salesChannelContext);
            $encodedToken = $this->encodeToken($token);
        } else {
            $encodedToken = $this->getOldToken($transaction, $finishUrl, $errorUrl, $salesChannelContext);
        }
        $returnUrl = $this->getReturnUrl($encodedToken);

        try {
            $paymentHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($transaction->getPaymentMethodId());
            if (!$paymentHandler) {
                throw PaymentException::unknownPaymentMethodById($transaction->getPaymentMethodId());
            }

            $transactionStruct = $this->paymentTransactionStructFactory->build($transaction->getId(), $salesChannelContext->getContext(), $returnUrl);
            $validationStruct = $transaction->getValidationData() ? new ArrayStruct($transaction->getValidationData()) : null;

            $response = $paymentHandler->pay($request, $transactionStruct, $salesChannelContext->getContext(), $validationStruct);
            if ($response instanceof RedirectResponse) {
                $encodedToken = null;
            }

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('An error occurred during processing the payment', ['orderTransactionId' => $transaction->getId(), 'exceptionMessage' => $e->getMessage(), 'exceptionTrace' => $e->getTraceAsString(), 'exception' => $e]);
            $this->transactionStateHandler->fail($transaction->getId(), $salesChannelContext->getContext());
            if ($errorUrl !== null) {
                $errorCode = $e instanceof HttpException ? $e->getErrorCode() : PaymentException::PAYMENT_PROCESS_ERROR;

                return new RedirectResponse(\sprintf('%s%serror-code=%s', $errorUrl, parse_url($errorUrl, \PHP_URL_QUERY) ? '&' : '?', $errorCode));
            }

            throw $e;
        } finally {
            // has been nulled, if response is RedirectResponse, therefore we have a finalize step
            if ($encodedToken) {
                if (($token ?? null) instanceof PaymentToken && $token->jti !== null) {
                    $this->paymentTokenLifecycle->invalidateToken($token->jti);
                } else {
                    $this->tokenFactory?->invalidateToken($encodedToken);
                }
            }
        }
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-type-change - first parameter will become `PaymentToken $token` instead of being the last optional parameter
     * @deprecated tag:v6.8.0 - reason:return-type-change - will return `void` instead of `TokenStruct`
     *
     * new signature to copy: public function finalize(PaymentToken $token, Request $request, SalesChannelContext $context): void
     */
    public function finalize(TokenStruct $token, Request $request, SalesChannelContext $context /* , ?PaymentToken $paymentToken = null */): TokenStruct
    {
        // @deprecated tag:v6.8.0 - remove these two lines
        $oldToken = $token;
        $token = (\func_num_args() > 3 && \func_get_arg(3) instanceof PaymentToken) ? \func_get_arg(3) : $token;

        $transactionId = $token instanceof PaymentToken ? $token->transactionId : $token->getTransactionId();
        $paymentMethodId = $token instanceof PaymentToken ? $token->paymentMethodId : $token->getPaymentMethodId();

        // @deprecated tag:v6.8.0 - remove this if block, as both parameters are non-nullable in the PaymentToken
        if ($paymentMethodId === null || $transactionId === null) {
            \assert($token instanceof TokenStruct);
            throw PaymentException::invalidToken($token->getToken() ?? '');
        }

        $paymentHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethodId);
        if (!$paymentHandler) {
            throw PaymentException::unknownPaymentMethodById($paymentMethodId);
        }

        try {
            $transactionStruct = $this->paymentTransactionStructFactory->build($transactionId, $context->getContext());
            $paymentHandler->finalize($request, $transactionStruct, $context->getContext());
        } catch (\Throwable $e) {
            if ($e instanceof PaymentException && $e->getErrorCode() === PaymentException::PAYMENT_CUSTOMER_CANCELED_EXTERNAL) {
                $this->transactionStateHandler->cancel($transactionId, $context->getContext());
            } else {
                $this->logger->error('An error occurred during finalizing async payment', ['orderTransactionId' => $transactionId, 'exceptionMessage' => $e->getMessage(), 'exception' => $e]);
                $this->transactionStateHandler->fail($transactionId, $context->getContext());
            }

            if ($token instanceof PaymentToken) {
                throw $e;
            }

            $token->setException($e);
        } finally {
            if ($token instanceof PaymentToken && $token->jti !== null) {
                $this->paymentTokenLifecycle->invalidateToken($token->jti);
            }

            // @deprecated tag:v6.8.0 - remove this if block
            if ($token instanceof TokenStruct && $token->getToken() !== null) {
                $this->tokenFactory?->invalidateToken($token->getToken());
            }
        }

        return $oldToken;
    }

    public function validate(
        Cart $cart,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): ?Struct {
        try {
            $paymentHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($salesChannelContext->getPaymentMethod()->getId());
            if (!$paymentHandler) {
                throw PaymentException::unknownPaymentMethodById($salesChannelContext->getPaymentMethod()->getId());
            }

            $struct = $paymentHandler->validate($cart, $dataBag, $salesChannelContext);
            $cart->getTransactions()->first()?->setValidationStruct($struct);

            return $struct;
        } catch (\Throwable $e) {
            $this->logger->error(
                'An error occurred during processing the validation of the payment. The order has not been placed yet.',
                ['customerId' => $salesChannelContext->getCustomerId(), 'exceptionMessage' => $e->getMessage(), 'exception' => $e]
            );

            throw $e;
        }
    }

    private function getCurrentOrderTransaction(string $orderId, Context $context): ?OrderTransactionEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('stateId', $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE)))
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(1);

        $transaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();

        if (!$transaction) {
            // check, if there are no transactions at all or just not with non-initial state
            $criteria->resetFilters();
            $criteria->addFilter(new EqualsFilter('orderId', $orderId));

            if ($this->orderTransactionRepository->searchIds($criteria, $context)->firstId()) {
                return null;
            }

            throw PaymentException::invalidOrder($orderId);
        }

        return $transaction;
    }

    private function getOldToken(OrderTransactionEntity $transaction, ?string $finishUrl, ?string $errorUrl, SalesChannelContext $salesChannelContext): string
    {
        if (!$this->tokenFactory) {
            throw new ServiceNotFoundException(JWTFactoryV2::class);
        }

        $paymentFinalizeTransactionTime = $this->systemConfigService->get('core.cart.paymentFinalizeTransactionTime', $salesChannelContext->getSalesChannelId());

        $paymentFinalizeTransactionTime = \is_numeric($paymentFinalizeTransactionTime)
            ? (int) $paymentFinalizeTransactionTime * 60
            : null;

        $tokenStruct = new TokenStruct(
            null,
            null,
            $transaction->getPaymentMethodId(),
            $transaction->getId(),
            $finishUrl,
            $paymentFinalizeTransactionTime,
            $errorUrl
        );

        return $this->tokenFactory->generateToken($tokenStruct);
    }

    private function getToken(OrderTransactionEntity $transaction, ?string $finishUrl, ?string $errorUrl, SalesChannelContext $salesChannelContext): PaymentToken
    {
        $token = new PaymentToken();
        $token->paymentMethodId = $transaction->getPaymentMethodId();
        $token->salesChannelId = $salesChannelContext->getSalesChannelId();
        $token->transactionId = $transaction->getId();
        $token->finishUrl = $finishUrl;
        $token->errorUrl = $errorUrl;

        return $token;
    }

    private function encodeToken(PaymentToken $token): string
    {
        $encoded = $this->paymentTokenGenerator->encode($token);

        if (!$token->jti || !$token->exp) {
            throw PaymentException::invalidToken($encoded);
        }

        $this->paymentTokenLifecycle->addToken($token->jti, $token->exp);

        return $encoded;
    }

    private function getReturnUrl(string $token): string
    {
        $parameter = ['_sw_payment_token' => $token];

        return $this->router->generate('payment.finalize.transaction', $parameter, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
