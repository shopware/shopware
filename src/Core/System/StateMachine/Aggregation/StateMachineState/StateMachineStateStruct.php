<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\StateMachine\StateMachineStruct;

class StateMachineStateStruct extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $technicalName;

    /**
     * @var string
     */
    protected $stateMachineId;

    /**
     * @var StateMachineStruct
     */
    protected $stateMachine;

    /**
     * @var Collection|null
     */
    protected $fromTransitions;

    /**
     * @var Collection|null
     */
    protected $toTransitions;

    /**
     * @var Collection|null
     */
    protected $initialStateStateMachines;

    /**
     * @var Collection
     */
    protected $translations;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var Collection|null
     */
    protected $orders;

    /**
     * @var Collection|null
     */
    protected $orderTransactions;

    /**
     * @var Collection|null
     */
    protected $orderDeliveries;

    public function getName(): string
    {
        return $this->name;
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

    public function getStateMachine(): StateMachineStruct
    {
        return $this->stateMachine;
    }

    public function setStateMachine(StateMachineStruct $stateMachine): void
    {
        $this->stateMachine = $stateMachine;
    }

    public function getFromTransitions(): ?Collection
    {
        return $this->fromTransitions;
    }

    public function setFromTransitions(Collection $fromTransitions): void
    {
        $this->fromTransitions = $fromTransitions;
    }

    public function getToTransitions(): ?Collection
    {
        return $this->toTransitions;
    }

    public function setToTransitions(Collection $toTransitions): void
    {
        $this->toTransitions = $toTransitions;
    }

    public function getInitialStateStateMachines(): ?Collection
    {
        return $this->initialStateStateMachines;
    }

    public function setInitialStateStateMachines(Collection $initialStateStateMachines): void
    {
        $this->initialStateStateMachines = $initialStateStateMachines;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function setTranslations(Collection $translations): void
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

    public function getOrders(): ?Collection
    {
        return $this->orders;
    }

    public function setOrders(Collection $orders): void
    {
        $this->orders = $orders;
    }

    public function getOrderTransactions(): ?Collection
    {
        return $this->orderTransactions;
    }

    public function setOrderTransactions(Collection $orderTransactions): void
    {
        $this->orderTransactions = $orderTransactions;
    }

    public function getOrderDeliveries(): ?Collection
    {
        return $this->orderDeliveries;
    }

    public function setOrderDeliveries(Collection $orderDeliveries): void
    {
        $this->orderDeliveries = $orderDeliveries;
    }
}
