<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;

#[Package('checkout')]
class PaymentRecurringProcessor
{
    /**
     * States that mean the payment service provider confirmed the payment. Reaching one of them while the handler
     * was still running makes the handler error obsolete: the provider accepted the payment - captured it, or in
     * the case of "authorized" committed to it - so the transaction must not be failed behind the provider's back,
     * and the caller must not be told the renewal failed.
     */
    private const CONFIRMED_STATES = [
        OrderTransactionStates::STATE_PAID,
        OrderTransactionStates::STATE_AUTHORIZED,
    ];

    /**
     * @internal
     *
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $orderTransactionRepository,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly OrderTransactionStateHandler $stateHandler,
        private readonly PaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly AbstractPaymentTransactionStructFactory $paymentTransactionStructFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function processRecurring(string $orderId, Context $context): void
    {
        $transaction = $this->getCurrentOrderTransaction($orderId, $context);

        try {
            $paymentHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($transaction->getPaymentMethodId());
            if (!$paymentHandler) {
                throw PaymentException::unknownPaymentMethodById($transaction->getPaymentMethodId());
            }

            if (!$paymentHandler->supports(PaymentHandlerType::RECURRING, $transaction->getPaymentMethodId(), $context)) {
                throw PaymentException::paymentTypeUnsupported($transaction->getPaymentMethodId(), PaymentHandlerType::RECURRING);
            }

            $struct = $this->paymentTransactionStructFactory->build($transaction->getId(), $context);
            $paymentHandler->recurring($struct, $context);
        } catch (\Throwable $e) {
            $this->logger->error('An error occurred during processing the payment', ['orderTransactionId' => $transaction->getId(), 'exceptionMessage' => $e->getMessage()]);

            try {
                $this->stateHandler->fail($transaction->getId(), $context, self::CONFIRMED_STATES);
            } catch (IllegalTransitionException $illegalTransition) {
                // The transaction left the states that can fail while the handler was running, so there is nothing
                // to fail. Report the handler error, not the follow-up error about the state machine.
                $this->logger->error(
                    'The order transaction could not be failed after the payment error',
                    ['orderTransactionId' => $transaction->getId(), 'exceptionMessage' => $illegalTransition->getMessage()]
                );

                throw $e;
            }

            // Asked after the guarded transition on purpose: the guard is what rules out failing a confirmed
            // transaction, and only once it has run is the state safe to interpret. Checking beforehand would leave
            // the window this guards against wide open.
            if ($this->isConfirmed($transaction->getId(), $context)) {
                $this->logger->info(
                    'The payment was confirmed while the recurring payment handler was running, the handler error is ignored',
                    ['orderTransactionId' => $transaction->getId(), 'exceptionMessage' => $e->getMessage()]
                );

                return;
            }

            throw $e;
        }
    }

    private function isConfirmed(string $transactionId, Context $context): bool
    {
        $criteria = (new Criteria([$transactionId]))->addAssociation('stateMachineState');

        $state = $this->orderTransactionRepository->search($criteria, $context)
            ->getEntities()
            ->get($transactionId)
            ?->getStateMachineState()
            ?->getTechnicalName();

        return \in_array($state, self::CONFIRMED_STATES, true);
    }

    private function getCurrentOrderTransaction(string $orderId, Context $context): OrderTransactionEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('stateId', $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE)))
            ->addFilter(new EqualsFilter('orderId', $orderId))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setLimit(1);

        $transaction = $this->orderTransactionRepository->search($criteria, $context)->getEntities()->first();

        if (!$transaction) {
            throw PaymentException::invalidOrder($orderId);
        }

        return $transaction;
    }
}
