<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\AbstractPaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[Package('checkout')]
class PaymentProcessor
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     *
     * @internal
     */
    public function __construct(
        private readonly TokenFactoryInterfaceV2 $tokenFactory,
        private readonly PaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly EntityRepository $orderTransactionRepository,
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly LoggerInterface $logger,
        private readonly AbstractPaymentTransactionStructFactory $paymentTransactionStructFactory,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly RouterInterface $router,
        private readonly SystemConfigService $systemConfigService,
        private readonly PaymentService $paymentService,
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
        $token = $this->getToken($transaction, $finishUrl, $errorUrl, $salesChannelContext);
        $returnUrl = $this->getReturnUrl($token);

        try {
            $paymentHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($transaction->getPaymentMethodId());
            if (!$paymentHandler) {
                throw PaymentException::unknownPaymentMethodById($transaction->getPaymentMethodId());
            }

            // @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
            if (!$paymentHandler instanceof AbstractPaymentHandler) {
                return $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag($request->request->all()), $salesChannelContext, $finishUrl, $errorUrl);
            }

            $transactionStruct = $this->paymentTransactionStructFactory->build($transaction->getId(), $salesChannelContext->getContext(), $returnUrl);
            $validationStruct = $transaction->getValidationData() ? new ArrayStruct($transaction->getValidationData()) : null;

            $response = $paymentHandler->pay($request, $transactionStruct, $salesChannelContext->getContext(), $validationStruct);
            if ($response instanceof RedirectResponse) {
                $token = null;
            }

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('An error occurred during processing the payment', ['orderTransactionId' => $transaction->getId(), 'exceptionMessage' => $e->getMessage()]);
            $this->transactionStateHandler->fail($transaction->getId(), $salesChannelContext->getContext());
            if ($errorUrl !== null) {
                $errorCode = $e instanceof HttpException ? $e->getErrorCode() : PaymentException::PAYMENT_PROCESS_ERROR;

                return new RedirectResponse(\sprintf('%s%serror-code=%s', $errorUrl, parse_url($errorUrl, \PHP_URL_QUERY) ? '&' : '?', $errorCode));
            }

            throw $e;
        } finally {
            if ($token) {
                // has been nulled, if response is RedirectResponse, therefore we have a finalize step
                $this->tokenFactory->invalidateToken($token);
            }
        }
    }

    public function finalize(TokenStruct $token, Request $request, SalesChannelContext $context): TokenStruct
    {
        if ($token->getPaymentMethodId() === null || $token->getTransactionId() === null) {
            throw PaymentException::invalidToken($token->getToken() ?? '');
        }

        $paymentHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($token->getPaymentMethodId());
        if (!$paymentHandler) {
            throw PaymentException::unknownPaymentMethodById($token->getPaymentMethodId());
        }

        // @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
        if (!$paymentHandler instanceof AbstractPaymentHandler) {
            $paymentToken = $token->getToken();
            if ($paymentToken === null) {
                throw PaymentException::invalidToken('');
            }

            return $this->paymentService->finalizeTransaction($paymentToken, $request, $context);
        }

        try {
            $transactionStruct = $this->paymentTransactionStructFactory->build($token->getTransactionId(), $context->getContext());
            $paymentHandler->finalize($request, $transactionStruct, $context->getContext());
        } catch (\Throwable $e) {
            if ($e instanceof PaymentException && $e->getErrorCode() === PaymentException::PAYMENT_CUSTOMER_CANCELED_EXTERNAL) {
                $this->transactionStateHandler->cancel($token->getTransactionId(), $context->getContext());
            } else {
                $this->logger->error('An error occurred during finalizing async payment', ['orderTransactionId' => $token->getTransactionId(), 'exceptionMessage' => $e->getMessage(), 'exception' => $e]);
                $this->transactionStateHandler->fail($token->getTransactionId(), $context->getContext());
            }

            // @deprecated tag:v6.7.0 - remove, $token will accept Throwable
            if (!$e instanceof \Exception) {
                $e = new \Exception($e->getMessage(), $e->getCode(), $e);
            }

            $token->setException($e);
        } finally {
            if ($token->getToken() !== null) {
                $this->tokenFactory->invalidateToken($token->getToken());
            }
        }

        return $token;
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

            if (!($paymentHandler instanceof PreparedPaymentHandlerInterface) && !($paymentHandler instanceof AbstractPaymentHandler)) {
                return null;
            }

            $struct = $paymentHandler->validate($cart, $dataBag, $salesChannelContext);
            $cart->getTransactions()->first()?->setValidationStruct($struct);

            return $struct;
        } catch (\Throwable $e) {
            $this->logger->error(
                'An error occurred during processing the validation of the payment. The order has not been placed yet.',
                ['customerId' => $salesChannelContext->getCustomer()?->getId(), 'exceptionMessage' => $e->getMessage(), 'exception' => $e]
            );

            throw $e;
        }
    }

    private function getCurrentOrderTransaction(string $orderId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stateId', $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE)));
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

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

    private function getToken(OrderTransactionEntity $transaction, ?string $finishUrl, ?string $errorUrl, SalesChannelContext $salesChannelContext): string
    {
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

    private function getReturnUrl(string $token): string
    {
        $parameter = ['_sw_payment_token' => $token];

        return $this->router->generate('payment.finalize.transaction', $parameter, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
