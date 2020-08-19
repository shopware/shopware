<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderRefund;

use Shopware\Core\Checkout\Order\Aggregate\OrderRefundPosition\OrderRefundPositionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class OrderRefundEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string|null
     */
    protected $transactionId;

    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var float
     */
    protected $amount;

    /**
     * @var PaymentMethodEntity|null
     */
    protected $paymentMethod;

    /**
     * @var OrderEntity|null
     */
    protected $order;

    /**
     * @var OrderTransactionEntity|null
     */
    protected $transaction;

    /***
     * @var StateMachineStateEntity|null
     */
    protected $stateMachineState;

    /**
     * @var string
     */
    protected $stateId;

    /**
     * @var array|null
     */
    protected $customFields;

    /**
     * @var string|null
     */
    protected $transactionCaptureId;

    /**
     * @var OrderTransactionCaptureEntity|null
     */
    protected $transactionCapture;

    /**
     * @var OrderRefundPositionCollection|null
     */
    protected $positions;

    /**
     * @var array|null
     */
    protected $options;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getPaymentMethod(): ?PaymentMethodEntity
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getTransaction(): ?OrderTransactionEntity
    {
        return $this->transaction;
    }

    public function setTransaction(?OrderTransactionEntity $transaction): void
    {
        $this->transaction = $transaction;
    }

    public function getStateMachineState(): ?StateMachineStateEntity
    {
        return $this->stateMachineState;
    }

    public function setStateMachineState(StateMachineStateEntity $stateMachineState): void
    {
        $this->stateMachineState = $stateMachineState;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        $this->stateId = $stateId;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getTransactionCaptureId(): ?string
    {
        return $this->transactionCaptureId;
    }

    public function setTransactionCaptureId(?string $transactionCaptureId): void
    {
        $this->transactionCaptureId = $transactionCaptureId;
    }

    public function getTransactionCapture(): ?OrderTransactionCaptureEntity
    {
        return $this->transactionCapture;
    }

    public function setTransactionCapture(OrderTransactionCaptureEntity $transactionCapture): void
    {
        $this->transactionCapture = $transactionCapture;
    }

    public function getPositions(): ?OrderRefundPositionCollection
    {
        return $this->positions;
    }

    public function setPositions(OrderRefundPositionCollection $positions): void
    {
        $this->positions = $positions;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): void
    {
        $this->options = $options;
    }
}
