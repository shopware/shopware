<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Checkout\Order\Aggregate\OrderRefund\OrderRefundCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class OrderTransactionCaptureEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $transactionId;

    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string|null
     */
    protected $externalReference;

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
     * @var OrderRefundCollection|null
     */
    protected $refunds;

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
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

    public function getRefunds(): ?OrderRefundCollection
    {
        return $this->refunds;
    }

    public function setRefunds(OrderRefundCollection $refunds): void
    {
        $this->refunds = $refunds;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): void
    {
        $this->externalReference = $externalReference;
    }
}
