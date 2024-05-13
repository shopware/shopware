<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Event\RecurringPaymentOrderCriteriaEvent;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.7.0 - will be removed, use `PaymentProcessor` instead
 */
#[Package('checkout')]
class PaymentRecurringProcessor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly OrderTransactionStateHandler $stateHandler,
        private readonly PaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly AbstractPaymentTransactionStructFactory $paymentTransactionStructFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function processRecurring(string $orderId, Context $context): void
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('orderCustomer.salutation');
        $criteria->addAssociation('transactions.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('language');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('lineItems');
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        $this->eventDispatcher->dispatch(new RecurringPaymentOrderCriteriaEvent($orderId, $criteria, $context));

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order) {
            throw OrderException::orderNotFound($orderId);
        }

        $transactions = $order->getTransactions();
        if ($transactions === null) {
            throw OrderException::missingTransactions($orderId);
        }

        $transactions = $transactions->filterByStateId(
            $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE)
        );

        $transaction = $transactions->last();
        if ($transaction === null) {
            return;
        }

        $paymentMethod = $transaction->getPaymentMethod();
        if ($paymentMethod === null) {
            throw PaymentException::unknownPaymentMethodById($transaction->getPaymentMethodId());
        }

        $paymentHandler = $this->paymentHandlerRegistry->getRecurringPaymentHandler($paymentMethod->getId());
        if (!$paymentHandler) {
            throw PaymentException::unknownPaymentMethodByHandlerIdentifier($paymentMethod->getHandlerIdentifier());
        }

        $struct = $this->paymentTransactionStructFactory->recurring($transaction, $order);

        try {
            $paymentHandler->captureRecurring($struct, $context);
        } catch (PaymentException $e) {
            $this->stateHandler->fail($transaction->getId(), $context);

            throw $e;
        }
    }
}
