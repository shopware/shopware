<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund;

use Shopware\Core\Checkout\Order\Aggregate\OrderRefund\OrderRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderRefund\OrderRefundStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderRefund\OrderRefundStates;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Refund\Exception\InvalidOrderRefundException;
use Shopware\Core\Checkout\Refund\Exception\PaymentRefundHandlerNotFoundException;
use Shopware\Core\Checkout\Refund\Exception\PaymentRefundProcessException;
use Shopware\Core\Checkout\Refund\Exception\PaymentRefundUnsupportedException;
use Shopware\Core\Checkout\Refund\RefundHandler\PaymentRefundHandlerRegistry;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

class PaymentRefundProcessor
{
    /**
     * @var OrderRefundStateHandler
     */
    private $refundStateHandler;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRefundRepository;

    /**
     * @var PaymentRefundHandlerRegistry
     */
    private $paymentRefundHandlerRegistry;

    public function __construct(
        EntityRepositoryInterface $orderRefundRepository,
        PaymentRefundHandlerRegistry $paymentRefundHandlerRegistry,
        OrderRefundStateHandler $refundStateHandler
    ) {
        $this->orderRefundRepository = $orderRefundRepository;
        $this->paymentRefundHandlerRegistry = $paymentRefundHandlerRegistry;
        $this->refundStateHandler = $refundStateHandler;
    }

    /**
     * @throws InvalidOrderRefundException
     * @throws PaymentRefundProcessException
     */
    public function processRefund(
        string $orderRefundId,
        Context $context
    ): void {
        if (!Uuid::isValid($orderRefundId)) {
            throw new InvalidOrderRefundException($orderRefundId);
        }

        $criteria = new Criteria([$orderRefundId]);
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('order');

        /** @var OrderRefundEntity|null $orderRefund */
        $orderRefund = $this->orderRefundRepository->search($criteria, $context)->first();

        if (!$orderRefund) {
            throw new InvalidOrderRefundException($orderRefundId);
        }
        $order = $orderRefund->getOrder();
        if (!$order) {
            throw new InvalidOrderException($orderRefund->getOrderId());
        }
        $stateMachineState = $orderRefund->getStateMachineState();
        if (!$stateMachineState || $stateMachineState->getTechnicalName() !== OrderRefundStates::STATE_OPEN) {
            throw new InvalidOrderRefundException($orderRefundId);
        }

        $paymentMethod = $orderRefund->getPaymentMethod();
        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($orderRefund->getPaymentMethodId());
        }
        $refundHandlerIdentifier = $paymentMethod->getRefundHandlerIdentifier();
        if (!$refundHandlerIdentifier) {
            throw new PaymentRefundUnsupportedException($orderRefund->getPaymentMethodId());
        }

        $paymentRefundHandler = $this->paymentRefundHandlerRegistry->getRefundHandler($refundHandlerIdentifier);
        if (!$paymentRefundHandler) {
            throw new PaymentRefundHandlerNotFoundException($refundHandlerIdentifier);
        }

        try {
            $paymentRefundHandler->refund($orderRefundId, $context);
        } catch (PaymentRefundProcessException $e) {
            $this->refundStateHandler->fail($orderRefundId, $context);

            throw $e;
        }
    }
}
