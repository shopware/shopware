<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateStruct;

class StateMachineTransitionStruct extends Entity
{
    /**
     * @var string
     */
    protected $actionName;

    /**
     * @var string
     */
    protected $stateMachineId;

    /**
     * @var StateMachineStateStruct
     */
    protected $stateMachine;

    /**
     * @var string
     */
    protected $fromStateId;

    /**
     * @var StateMachineStateStruct
     */
    protected $fromState;

    /**
     * @var string
     */
    protected $toStateId;

    /**
     * @var StateMachineStateStruct
     */
    protected $toState;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getStateMachineId(): string
    {
        return $this->stateMachineId;
    }

    public function setStateMachineId(string $stateMachineId): void
    {
        $this->stateMachineId = $stateMachineId;
    }

    public function getStateMachine(): StateMachineStateStruct
    {
        return $this->stateMachine;
    }

    public function setStateMachine(StateMachineStateStruct $stateMachine): void
    {
        $this->stateMachine = $stateMachine;
    }

    public function getFromStateId(): string
    {
        return $this->fromStateId;
    }

    public function setFromStateId(string $fromStateId): void
    {
        $this->fromStateId = $fromStateId;
    }

    public function getFromState(): StateMachineStateStruct
    {
        return $this->fromState;
    }

    public function setFromState(StateMachineStateStruct $fromState): void
    {
        $this->fromState = $fromState;
    }

    public function getToStateId(): string
    {
        return $this->toStateId;
    }

    public function setToStateId(string $toStateId): void
    {
        $this->toStateId = $toStateId;
    }

    public function getToState(): StateMachineStateStruct
    {
        return $this->toState;
    }

    public function setToState(StateMachineStateStruct $toState): void
    {
        $this->toState = $toState;
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

    public function getActionName(): string
    {
        return $this->actionName;
    }

    public function setActionName(string $actionName): void
    {
        $this->actionName = $actionName;
    }
}
