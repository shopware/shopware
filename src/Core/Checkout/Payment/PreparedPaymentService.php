<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PreparedPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\Exception\ValidatePreparedPaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;

#[Package('checkout')]
class PreparedPaymentService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly PaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly EntityRepository $appPaymentMethodRepository,
        private readonly LoggerInterface $logger,
        private readonly InitialStateIdLoader $initialStateIdLoader
    ) {
    }

    public function handlePreOrderPayment(
        Cart $cart,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): ?Struct {
        try {
            $paymentHandler = $this->getPaymentHandlerFromSalesChannelContext($salesChannelContext);
            if (!$paymentHandler) {
                throw new UnknownPaymentMethodException($salesChannelContext->getPaymentMethod()->getId());
            }

            if (!($paymentHandler instanceof PreparedPaymentHandlerInterface)) {
                return null;
            }

            return $paymentHandler->validate($cart, $dataBag, $salesChannelContext);
        } catch (PaymentProcessException|ValidatePreparedPaymentException $e) {
            $customer = $salesChannelContext->getCustomer();
            $customerId = $customer !== null ? $customer->getId() : '';
            $this->logger->error('An error occurred during processing the validation of the payment. The order has not been placed yet.', ['customerId' => $customerId, 'exceptionMessage' => $e->getMessage()]);

            throw $e;
        }
    }

    public function handlePostOrderPayment(
        OrderEntity $order,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext,
        ?Struct $preOrderStruct
    ): void {
        try {
            $transaction = $this->getTransaction($order, $salesChannelContext);
            if ($transaction === null) {
                return;
            }

            $paymentHandler = $this->getPaymentHandlerFromTransaction($transaction);

            if (!($paymentHandler instanceof PreparedPaymentHandlerInterface)
                || $preOrderStruct === null) {
                return;
            }

            $preparedTransactionStruct = new PreparedPaymentTransactionStruct($transaction, $order);
            $paymentHandler->capture($preparedTransactionStruct, $dataBag, $salesChannelContext, $preOrderStruct);
        } catch (PaymentProcessException $e) {
            $this->logger->error('An error occurred during processing the capture of the payment. The order has been placed.', ['orderId' => $order->getId(), 'exceptionMessage' => $e->getMessage()]);

            throw $e;
        }
    }

    private function getTransaction(OrderEntity $order, SalesChannelContext $salesChannelContext): ?OrderTransactionEntity
    {
        $transactions = $order->getTransactions();
        if ($transactions === null) {
            throw new InvalidOrderException($order->getId());
        }

        $transactions = $transactions->filterByStateId(
            $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE)
        );

        return $transactions->last();
    }

    private function getPaymentHandlerFromTransaction(OrderTransactionEntity $transaction): PaymentHandlerInterface
    {
        $paymentMethod = $transaction->getPaymentMethod();
        if ($paymentMethod === null) {
            throw new UnknownPaymentMethodException($transaction->getPaymentMethodId());
        }

        $paymentHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());
        if (!$paymentHandler) {
            throw new UnknownPaymentMethodException($paymentMethod->getId());
        }

        return $paymentHandler;
    }

    private function getPaymentHandlerFromSalesChannelContext(SalesChannelContext $salesChannelContext): ?PaymentHandlerInterface
    {
        $paymentMethod = $salesChannelContext->getPaymentMethod();

        if (($appPaymentMethod = $paymentMethod->getAppPaymentMethod()) && $appPaymentMethod->getApp()) {
            return $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());
        }

        $criteria = new Criteria();
        $criteria->setTitle('prepared-payment-handler');
        $criteria->addAssociation('app');
        $criteria->addFilter(new EqualsFilter('paymentMethodId', $paymentMethod->getId()));

        $appPaymentMethod = $this->appPaymentMethodRepository->search($criteria, $salesChannelContext->getContext())->first();
        $paymentMethod->setAppPaymentMethod($appPaymentMethod);

        return $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod->getId());
    }
}
