<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\StateMachineEntity;

#[Package('customer-order')]
class OrderTransactionCaptureEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    protected string $orderTransactionId;

    protected string $stateId;

    protected ?string $externalReference = null;

    protected CalculatedPrice $amount;

    protected ?OrderTransactionEntity $transaction = null;

    protected ?StateMachineEntity $stateMachineState = null;

    protected ?OrderTransactionCaptureRefundCollection $refunds = null;

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }

    public function setOrderTransactionId(string $orderTransactionId): void
    {
        $this->orderTransactionId = $orderTransactionId;
    }

    public function getStateId(): string
    {
        return $this->stateId;
    }

    public function setStateId(string $stateId): void
    {
        $this->stateId = $stateId;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): void
    {
        $this->externalReference = $externalReference;
    }

    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    public function setAmount(CalculatedPrice $amount): void
    {
        $this->amount = $amount;
    }

    public function getTransaction(): ?OrderTransactionEntity
    {
        return $this->transaction;
    }

    public function setTransaction(?OrderTransactionEntity $transaction): void
    {
        $this->transaction = $transaction;
    }

    public function getStateMachineState(): ?StateMachineEntity
    {
        return $this->stateMachineState;
    }

    public function setStateMachineState(?StateMachineEntity $stateMachineState): void
    {
        $this->stateMachineState = $stateMachineState;
    }

    public function getRefunds(): ?OrderTransactionCaptureRefundCollection
    {
        return $this->refunds;
    }

    public function setRefunds(OrderTransactionCaptureRefundCollection $refunds): void
    {
        $this->refunds = $refunds;
    }
}
