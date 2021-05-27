<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PaymentService
{
    /**
     * @var PaymentTransactionChainProcessor
     */
    private $paymentProcessor;

    /**
     * @var TokenFactoryInterfaceV2
     */
    private $tokenFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var PaymentHandlerRegistry
     */
    private $paymentHandlerRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $contextService;

    public function __construct(
        PaymentTransactionChainProcessor $paymentProcessor,
        TokenFactoryInterfaceV2 $tokenFactory,
        EntityRepositoryInterface $paymentMethodRepository,
        PaymentHandlerRegistry $paymentHandlerRegistry,
        EntityRepositoryInterface $orderTransactionRepository,
        OrderTransactionStateHandler $transactionStateHandler,
        LoggerInterface $logger,
        EntityRepositoryInterface $orderRepository,
        SalesChannelContextServiceInterface $contextService
    ) {
        $this->paymentProcessor = $paymentProcessor;
        $this->tokenFactory = $tokenFactory;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentHandlerRegistry = $paymentHandlerRegistry;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->contextService = $contextService;
    }

    /**
     * @throws AsyncPaymentProcessException
     * @throws InvalidOrderException
     * @throws SyncPaymentProcessException
     * @throws UnknownPaymentMethodException
     */
    public function handlePaymentByOrder(
        string $orderId,
        RequestDataBag $dataBag,
        SalesChannelContext $context,
        ?string $finishUrl = null,
        ?string $errorUrl = null
    ): ?RedirectResponse {
        if (!Uuid::isValid($orderId)) {
            throw new InvalidOrderException($orderId);
        }

        $order = $this->orderRepository
            ->search(new Criteria([$orderId]), $context->getContext())
            ->first();

        if ($order === null) {
            throw new InvalidOrderException($orderId);
        }

        if ($context->getCurrency()->getId() !== $order->getCurrencyId()) {
            $context = $this->contextService->get(
                new SalesChannelContextServiceParameters(
                    $context->getSalesChannelId(),
                    $context->getToken(),
                    $context->getContext()->getLanguageId(),
                    $order->getCurrencyId()
                )
            );
        }

        try {
            return $this->paymentProcessor->process($orderId, $dataBag, $context, $finishUrl, $errorUrl);
        } catch (PaymentProcessException $e) {
            $transactionId = $e->getOrderTransactionId();
            $this->logger->error('An error occurred during processing the payment', ['orderTransactionId' => $transactionId, 'exceptionMessage' => $e->getMessage()]);
            $this->transactionStateHandler->fail($transactionId, $context->getContext());
            if ($errorUrl !== null) {
                $errorUrl .= (parse_url($errorUrl, \PHP_URL_QUERY) ? '&' : '?') . 'error-code=' . $e->getErrorCode();

                return new RedirectResponse($errorUrl);
            }

            throw $e;
        }
    }

    /**
     * @throws AsyncPaymentFinalizeException
     * @throws InvalidTransactionException
     * @throws TokenExpiredException
     * @throws UnknownPaymentMethodException
     */
    public function finalizeTransaction(string $paymentToken, Request $request, SalesChannelContext $context): TokenStruct
    {
        $token = $this->parseToken($paymentToken);

        $transactionId = $token->getTransactionId();

        if ($transactionId === null || !Uuid::isValid($transactionId)) {
            throw new AsyncPaymentProcessException((string) $transactionId, "Payment JWT didn't contain a valid orderTransactionId");
        }

        $transaction = $this->getPaymentTransactionStruct($transactionId, $context->getContext());

        $paymentHandler = $this->getPaymentHandlerById($token->getPaymentMethodId(), $context->getContext());

        if (!$paymentHandler) {
            throw new UnknownPaymentMethodException($token->getPaymentMethodId());
        }

        try {
            $paymentHandler->finalize($transaction, $request, $context);
        } catch (CustomerCanceledAsyncPaymentException $e) {
            $this->transactionStateHandler->cancel($transactionId, $context->getContext());
            $token->setException($e);
        } catch (PaymentProcessException $e) {
            $this->logger->error('An error occurred during finalizing async payment', ['orderTransactionId' => $transactionId, 'exceptionMessage' => $e->getMessage()]);
            $this->transactionStateHandler->fail($transactionId, $context->getContext());
            $token->setException($e);
        } finally {
            $this->tokenFactory->invalidateToken($token->getToken());
        }

        return $token;
    }

    /**
     * @throws TokenExpiredException
     */
    private function parseToken(string $token): TokenStruct
    {
        $tokenStruct = $this->tokenFactory->parseToken($token);

        if ($tokenStruct->isExpired()) {
            throw new TokenExpiredException($tokenStruct->getToken());
        }

        return $tokenStruct;
    }

    /**
     * @throws UnknownPaymentMethodException
     */
    private function getPaymentHandlerById(string $paymentMethodId, Context $context): ?AsynchronousPaymentHandlerInterface
    {
        $criteria = new Criteria([$paymentMethodId]);
        $criteria->addAssociation('appPaymentMethod.app');
        $paymentMethods = $this->paymentMethodRepository->search($criteria, $context);

        /** @var PaymentMethodEntity|null $paymentMethod */
        $paymentMethod = $paymentMethods->get($paymentMethodId);
        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        return $this->paymentHandlerRegistry->getAsyncHandlerForPaymentMethod($paymentMethod);
    }

    /**
     * @throws InvalidTransactionException
     */
    private function getPaymentTransactionStruct(string $orderTransactionId, Context $context): AsyncPaymentTransactionStruct
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociation('paymentMethod.appPaymentMethod.app');
        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction === null) {
            throw new InvalidTransactionException($orderTransactionId);
        }

        return new AsyncPaymentTransactionStruct($orderTransaction, $orderTransaction->getOrder(), '');
    }
}
