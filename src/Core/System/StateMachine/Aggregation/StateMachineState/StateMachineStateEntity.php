<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\StateMachineEntity;

#[Package('checkout')]
class StateMachineStateEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @deprecated tag:v6.7.0 - Type will be nullable. Also, it will be natively typed to enforce strict data type checking.
     *
     * @var string|null
     */
    protected $name;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $technicalName;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $stateMachineId;

    /**
     * @var StateMachineEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $stateMachine;

    /**
     * @var StateMachineTransitionCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $fromStateMachineTransitions;

    /**
     * @var StateMachineTransitionCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $toStateMachineTransitions;

    /**
     * @var StateMachineStateTranslationCollection
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $translations;

    /**
     * @var OrderCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $orders;

    protected ?OrderTransactionCaptureCollection $orderTransactionCaptures = null;

    protected ?OrderTransactionCaptureRefundCollection $orderTransactionCaptureRefunds = null;

    /**
     * @var OrderTransactionCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $orderTransactions;

    /**
     * @var OrderDeliveryCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $orderDeliveries;

    /**
     * @var StateMachineHistoryCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $fromStateMachineHistoryEntries;

    /**
     * @var StateMachineHistoryCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $toStateMachineHistoryEntries;

    public function getToStateMachineHistoryEntries(): ?StateMachineHistoryCollection
    {
        return $this->toStateMachineHistoryEntries;
    }

    public function setToStateMachineHistoryEntries(StateMachineHistoryCollection $toStateMachineHistoryEntries): void
    {
        $this->toStateMachineHistoryEntries = $toStateMachineHistoryEntries;
    }

    public function getFromStateMachineHistoryEntries(): ?StateMachineHistoryCollection
    {
        return $this->fromStateMachineHistoryEntries;
    }

    public function setFromStateMachineHistoryEntries(StateMachineHistoryCollection $fromStateMachineHistoryEntries): void
    {
        $this->fromStateMachineHistoryEntries = $fromStateMachineHistoryEntries;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will also return null
     * return type will be ?string in v6.7.0.0
     */
    public function getName(): string
    {
        /**
         * @deprecated tag:v6.7.0
         * remove the null-check
         * return $this->name;
         */
        return $this->name ?? '';
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStateMachineId(): string
    {
        return $this->stateMachineId;
    }

    public function setStateMachineId(string $stateMachineId): void
    {
        $this->stateMachineId = $stateMachineId;
    }

    public function getStateMachine(): ?StateMachineEntity
    {
        return $this->stateMachine;
    }

    public function setStateMachine(StateMachineEntity $stateMachine): void
    {
        $this->stateMachine = $stateMachine;
    }

    public function getFromStateMachineTransitions(): ?StateMachineTransitionCollection
    {
        return $this->fromStateMachineTransitions;
    }

    public function setFromStateMachineTransitions(StateMachineTransitionCollection $fromStateMachineTransitions): void
    {
        $this->fromStateMachineTransitions = $fromStateMachineTransitions;
    }

    public function getToStateMachineTransitions(): ?StateMachineTransitionCollection
    {
        return $this->toStateMachineTransitions;
    }

    public function setToStateMachineTransitions(StateMachineTransitionCollection $toStateMachineTransitions): void
    {
        $this->toStateMachineTransitions = $toStateMachineTransitions;
    }

    public function getTranslations(): StateMachineStateTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(StateMachineStateTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getOrders(): ?OrderCollection
    {
        return $this->orders;
    }

    public function setOrders(OrderCollection $orders): void
    {
        $this->orders = $orders;
    }

    public function getOrderTransactionCaptures(): ?OrderTransactionCaptureCollection
    {
        return $this->orderTransactionCaptures;
    }

    public function setOrderTransactionCaptures(OrderTransactionCaptureCollection $orderTransactionCaptures): void
    {
        $this->orderTransactionCaptures = $orderTransactionCaptures;
    }

    public function getOrderTransactionCaptureRefunds(): ?OrderTransactionCaptureRefundCollection
    {
        return $this->orderTransactionCaptureRefunds;
    }

    public function setOrderTransactionCaptureRefunds(OrderTransactionCaptureRefundCollection $orderTransactionCaptureRefunds): void
    {
        $this->orderTransactionCaptureRefunds = $orderTransactionCaptureRefunds;
    }

    public function getOrderTransactions(): ?OrderTransactionCollection
    {
        return $this->orderTransactions;
    }

    public function setOrderTransactions(OrderTransactionCollection $orderTransactions): void
    {
        $this->orderTransactions = $orderTransactions;
    }

    public function getOrderDeliveries(): ?OrderDeliveryCollection
    {
        return $this->orderDeliveries;
    }

    public function setOrderDeliveries(OrderDeliveryCollection $orderDeliveries): void
    {
        $this->orderDeliveries = $orderDeliveries;
    }
}
