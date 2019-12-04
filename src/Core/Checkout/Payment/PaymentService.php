<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionChainProcessor;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterface;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\TokenExpiredException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
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
     * @var TokenFactoryInterface
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
     * @var OrderConverter
     */
    private $orderConverter;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(
        PaymentTransactionChainProcessor $paymentProcessor,
        TokenFactoryInterface $tokenFactory,
        EntityRepositoryInterface $paymentMethodRepository,
        PaymentHandlerRegistry $paymentHandlerRegistry,
        EntityRepositoryInterface $orderTransactionRepository,
        OrderTransactionStateHandler $transactionStateHandler,
        OrderConverter $orderConverter,
        CartService $cartService
    ) {
        $this->paymentProcessor = $paymentProcessor;
        $this->tokenFactory = $tokenFactory;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentHandlerRegistry = $paymentHandlerRegistry;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->orderConverter = $orderConverter;
        $this->cartService = $cartService;
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
        ?string $finishUrl = null
    ): ?RedirectResponse {
        if (!Uuid::isValid($orderId)) {
            throw new InvalidOrderException($orderId);
        }

        try {
            return $this->paymentProcessor->process($orderId, $dataBag, $context, $finishUrl);
        } catch (AsyncPaymentProcessException | SyncPaymentProcessException $e) {
            $this->cancelOrderTransaction($e->getOrderTransactionId(), $context->getContext());
            $this->recoverCart($e->getOrderTransactionId(), $context);

            throw $e;
        }
    }

    /**
     * @throws AsyncPaymentFinalizeException
     * @throws CustomerCanceledAsyncPaymentException
     * @throws InvalidTransactionException
     * @throws TokenExpiredException
     * @throws UnknownPaymentMethodException
     */
    public function finalizeTransaction(
        string $paymentToken,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): TokenStruct {
        $paymentTokenStruct = $this->parseToken($paymentToken);
        $transactionId = $paymentTokenStruct->getTransactionId();
        $context = $salesChannelContext->getContext();
        $paymentTransactionStruct = $this->getPaymentTransactionStruct($transactionId, $context);

        $paymentHandler = $this->getPaymentHandlerById($paymentTokenStruct->getPaymentMethodId(), $context);

        if (!$paymentHandler) {
            throw new UnknownPaymentMethodException($paymentTokenStruct->getPaymentMethodId());
        }

        try {
            $paymentHandler->finalize($paymentTransactionStruct, $request, $salesChannelContext);
        } catch (CustomerCanceledAsyncPaymentException | AsyncPaymentFinalizeException $e) {
            $this->cancelOrderTransaction($e->getOrderTransactionId(), $context);
            $this->recoverCart($e->getOrderTransactionId(), $salesChannelContext);

            throw $e;
        }

        return $paymentTokenStruct;
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

        $this->tokenFactory->invalidateToken($tokenStruct->getToken());

        return $tokenStruct;
    }

    /**
     * @throws UnknownPaymentMethodException
     */
    private function getPaymentHandlerById(string $paymentMethodId, Context $context): ?AsynchronousPaymentHandlerInterface
    {
        $paymentMethods = $this->paymentMethodRepository->search(new Criteria([$paymentMethodId]), $context);

        /** @var PaymentMethodEntity|null $paymentMethod */
        $paymentMethod = $paymentMethods->get($paymentMethodId);
        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        return $this->paymentHandlerRegistry->getAsyncHandler($paymentMethod->getHandlerIdentifier());
    }

    /**
     * @throws InvalidTransactionException
     */
    private function getPaymentTransactionStruct(string $orderTransactionId, Context $context): AsyncPaymentTransactionStruct
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');
        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if ($orderTransaction === null) {
            throw new InvalidTransactionException($orderTransactionId);
        }

        return new AsyncPaymentTransactionStruct($orderTransaction, $orderTransaction->getOrder(), '');
    }

    private function cancelOrderTransaction(string $transactionId, Context $context): void
    {
        $this->transactionStateHandler->cancel($transactionId, $context);
    }

    private function recoverCart(string $transactionId, SalesChannelContext $context): void
    {
        $criteria = new Criteria([$transactionId]);
        $criteria
            ->addAssociation('order.lineItems.orderDeliveryPositions')
            ->addAssociation('order.deliveries.positions.orderLineItem')
            ->addAssociation('order.deliveries.shippingMethod')
            ->addAssociation('order.deliveries.shippingOrderAddress.country')
            ->addAssociation('order.deliveries.shippingOrderAddress.countryState')
            ->addAssociation('order.orderCustomer.activeBillingAddress')
            ->addAssociation('order.transactions');
        /** @var OrderTransactionEntity|null $transaction */
        $transaction = $this->orderTransactionRepository->search($criteria, $context->getContext())->first();

        if ($transaction && $transaction->getOrder() instanceof OrderEntity) {
            $cart = $this->orderConverter->convertToCart($transaction->getOrder(), $context->getContext());
            $cart->setToken($context->getToken());
            $cart->setName(CartService::SALES_CHANNEL);
            $this->cartService->recalculate($cart, $context);
        }
    }
}
