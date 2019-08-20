<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PaymentTransactionChainProcessor
{
    /**
     * @var TokenFactoryInterface
     */
    private $tokenFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var PaymentHandlerRegistry
     */
    private $paymentHandlerRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderCustomerRepository;

    public function __construct(
        TokenFactoryInterface $tokenFactory,
        EntityRepositoryInterface $orderRepository,
        RouterInterface $router,
        PaymentHandlerRegistry $paymentHandlerRegistry,
        EntityRepositoryInterface $orderCustomerRepository
    ) {
        $this->tokenFactory = $tokenFactory;
        $this->orderRepository = $orderRepository;
        $this->router = $router;
        $this->paymentHandlerRegistry = $paymentHandlerRegistry;
        $this->orderCustomerRepository = $orderCustomerRepository;
    }

    /**
     * @throws AsyncPaymentProcessException
     * @throws InvalidOrderException
     * @throws SyncPaymentProcessException
     * @throws UnknownPaymentMethodException
     */
    public function process(
        string $orderId,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext,
        ?string $finishUrl = null
    ): ?RedirectResponse {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('lineItems');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        $transactions = $order->getTransactions();
        if ($transactions === null) {
            throw new InvalidOrderException($orderId);
        }

        $order->setOrderCustomer(
            $this->fetchCustomer($order->getId(), $salesChannelContext->getContext())
        );

        $transactions = $transactions->filterByState(OrderTransactionStates::STATE_OPEN);

        foreach ($transactions as $transaction) {
            $paymentMethod = $transaction->getPaymentMethod();
            if ($paymentMethod === null) {
                throw new UnknownPaymentMethodException($transaction->getPaymentMethodId());
            }

            $paymentHandler = $this->paymentHandlerRegistry->getHandler($paymentMethod->getHandlerIdentifier());

            if (!$paymentHandler) {
                throw new UnknownPaymentMethodException($paymentMethod->getHandlerIdentifier());
            }

            if ($paymentHandler instanceof SynchronousPaymentHandlerInterface) {
                $paymentTransaction = new SyncPaymentTransactionStruct($transaction, $order);
                $paymentHandler->pay($paymentTransaction, $dataBag, $salesChannelContext);

                return null;
            }

            $token = $this->tokenFactory->generateToken($transaction, $finishUrl);
            $returnUrl = $this->assembleReturnUrl($token);
            $paymentTransaction = new AsyncPaymentTransactionStruct($transaction, $order, $returnUrl);

            return $paymentHandler->pay($paymentTransaction, $dataBag, $salesChannelContext);
        }

        return null;
    }

    private function assembleReturnUrl(string $token): string
    {
        $parameter = ['_sw_payment_token' => $token];

        return $this->router->generate('payment.finalize.transaction', $parameter, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function fetchCustomer(string $orderId, Context $context): OrderCustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addAssociation('customer');
        $criteria->addAssociation('salutation');

        return $this->orderCustomerRepository
            ->search($criteria, $context)
            ->first();
    }
}
