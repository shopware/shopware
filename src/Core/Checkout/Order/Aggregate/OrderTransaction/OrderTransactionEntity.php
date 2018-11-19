<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use DateTime;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderTransactionEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var CalculatedPrice
     */
    protected $amount;

    /**
     * @var DateTime|null
     */
    protected $createdAt;

    /**
     * @var DateTime|null
     */
    protected $updatedAt;

    /**
     * @var PaymentMethodEntity|null
     */
    protected $paymentMethod;

    /**
     * @var OrderEntity|null
     */
    protected $order;

    /**
     * @var array|null
     */
    protected $details;

    /***
     * @var OrderTransactionStateEntity
     */
    protected $state;

    /**
     * @var string
     */
    protected $stateId;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    public function setAmount(CalculatedPrice $amount): void
    {
        $this->amount = $amount;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(array $details): void
    {
        $this->details = $details;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
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

    public function getState(): StateMachineStateEntity
    {
        return $this->state;
    }

    public function setState(StateMachineStateEntity $state): void
    {
        $this->state = $state;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        $this->stateId = $stateId;
    }
}
