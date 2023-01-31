<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[Package('customer-order')]
class OrderTransactionCaptureRefundEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    protected string $captureId;

    protected string $stateId;

    protected ?string $externalReference = null;

    protected ?string $reason = null;

    protected CalculatedPrice $amount;

    protected ?StateMachineStateEntity $stateMachineState = null;

    protected ?OrderTransactionCaptureEntity $transactionCapture = null;

    protected ?OrderTransactionCaptureRefundPositionCollection $positions = null;

    public function getCaptureId(): string
    {
        return $this->captureId;
    }

    public function setCaptureId(string $captureId): void
    {
        $this->captureId = $captureId;
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

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getAmount(): CalculatedPrice
    {
        return $this->amount;
    }

    public function setAmount(CalculatedPrice $amount): void
    {
        $this->amount = $amount;
    }

    public function getStateMachineState(): ?StateMachineStateEntity
    {
        return $this->stateMachineState;
    }

    public function setStateMachineState(?StateMachineStateEntity $stateMachineState): void
    {
        $this->stateMachineState = $stateMachineState;
    }

    public function getTransactionCapture(): ?OrderTransactionCaptureEntity
    {
        return $this->transactionCapture;
    }

    public function setTransactionCapture(?OrderTransactionCaptureEntity $transactionCapture): void
    {
        $this->transactionCapture = $transactionCapture;
    }

    public function getPositions(): ?OrderTransactionCaptureRefundPositionCollection
    {
        return $this->positions;
    }

    public function setPositions(OrderTransactionCaptureRefundPositionCollection $positions): void
    {
        $this->positions = $positions;
    }
}
