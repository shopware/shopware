<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AbstractPaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Event\FinalizePaymentOrderTransactionCriteriaEvent;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
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
     * @param EntityRepository<OrderCollection> $orderRepository
     *
     * @internal
     */
    public function __construct(
        private readonly PaymentTransactionChainProcessor $paymentProcessor,
        private readonly TokenFactoryInterfaceV2 $tokenFactory,
        private readonly PaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly EntityRepository $orderTransactionRepository,
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly LoggerInterface $logger,
        private readonly EntityRepository $orderRepository,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly AbstractPaymentTransactionStructFactory $paymentTransactionStructFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
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

        try {
            $paymentHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($transaction->getPaymentMethodId());
            if (!$paymentHandler) {
                throw PaymentException::unknownPaymentMethod($transaction->getPaymentMethodId());
            }

            // @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
            if (!$paymentHandler instanceof AbstractPaymentHandler) {
                return $this->paymentProcessor->process($orderId, new RequestDataBag($request->request->all()), $salesChannelContext, $finishUrl, $errorUrl);
            }

            $returnUrl = $this->getReturnUrl($transaction, $finishUrl, $errorUrl, $salesChannelContext);
            $transactionStruct = $this->paymentTransactionStructFactory->build($transaction->getId(), $returnUrl);
            $validateStruct = new ArrayStruct($transaction->getCustomFieldsValue('validateStruct'));

            return $paymentHandler->pay($request, $transactionStruct, $salesChannelContext, $validateStruct);
        } catch (\Throwable $e) {
            $this->logger->error('An error occurred during processing the payment', ['orderTransactionId' => $transaction->getId(), 'exceptionMessage' => $e->getMessage()]);
            $this->transactionStateHandler->fail($transaction->getId(), $salesChannelContext->getContext());
            if ($errorUrl !== null) {
                $errorUrl .= (parse_url($errorUrl, \PHP_URL_QUERY) ? '&' : '?') . 'error-code=' . $e->getErrorCode();

                return new RedirectResponse($errorUrl);
            }

            throw $e;
        }
    }

    public function finalize(string $paymentToken, Request $request, SalesChannelContext $context): TokenStruct
    {
        $token = $this->tokenFactory->parseToken($paymentToken);

        if ($token->isExpired()) {
            $token->setException(PaymentException::tokenExpired($paymentToken));
            if ($token->getToken() !== null) {
                $this->tokenFactory->invalidateToken($token->getToken());
            }

            return $token;
        }

        if ($token->getPaymentMethodId() === null) {
            throw PaymentException::invalidToken($paymentToken);
        }

        $transactionId = $token->getTransactionId();
        if ($transactionId === null) {
            throw PaymentException::asyncProcessInterrupted((string) $transactionId, 'Payment JWT didn\'t contain a valid orderTransactionId');
        }

        $paymentHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($token->getPaymentMethodId());
        if (!$paymentHandler) {
            throw PaymentException::unknownPaymentMethod($token->getPaymentMethodId());
        }

        // @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
        if (!$paymentHandler instanceof AbstractPaymentHandler) {
            return $this->paymentService->finalizeTransaction($paymentToken, $request, $context);
        }

        try {
            $transactionStruct = $this->paymentTransactionStructFactory->build($transactionId);
            $paymentHandler->finalize($request, $transactionStruct, $context);
        } catch (CustomerCanceledAsyncPaymentException $e) {
            $this->transactionStateHandler->cancel($transactionId, $context->getContext());
            $token->setException($e);
        } catch (\Throwable $e) {
            $this->logger->error('An error occurred during finalizing async payment', ['orderTransactionId' => $transactionId, 'exceptionMessage' => $e->getMessage()]);
            $this->transactionStateHandler->fail($transactionId, $context->getContext());
            $token->setException($e);
        } finally {
            if ($token->getToken() !== null) {
                $this->tokenFactory->invalidateToken($token->getToken());
            }
        }

        return $token;
    }

    private function getCurrentOrderTransaction(string $orderId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stateId', $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE)));
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if (!$transaction) {
            throw PaymentException::invalidOrder($orderId);
        }

        return $transaction;
    }

    public function getReturnUrl(OrderTransactionEntity $transaction, ?string $finishUrl, ?string $errorUrl, SalesChannelContext $salesChannelContext): string
    {
        $paymentFinalizeTransactionTime = $this->systemConfigService->get('core.cart.paymentFinalizeTransactionTime', $salesChannelContext->getSalesChannelId());

        if (\is_numeric($paymentFinalizeTransactionTime)) {
            $paymentFinalizeTransactionTime = (int)$paymentFinalizeTransactionTime;
            // setting is in minutes, token holds in seconds
            $paymentFinalizeTransactionTime *= 60;
        } else {
            $paymentFinalizeTransactionTime = null;
        }

        $tokenStruct = new TokenStruct(
            null,
            null,
            $transaction->getPaymentMethodId(),
            $transaction->getId(),
            $finishUrl,
            $paymentFinalizeTransactionTime,
            $errorUrl
        );

        $token = $this->tokenFactory->generateToken($tokenStruct);

        $parameter = ['_sw_payment_token' => $token];

        return $this->router->generate('payment.finalize.transaction', $parameter, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
