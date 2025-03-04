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
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;

#[Package('checkout')]
class PaymentRecurringProcessor
{
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
            $this->stateHandler->fail($transaction->getId(), $context);

            throw $e;
        }
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
