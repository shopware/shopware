<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class StateMachineTransitionEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $actionName;

    /**
     * @var string
     */
    protected $stateMachineId;

    /**
     * @var StateMachineStateEntity|null
     */
    protected $stateMachine;

    /**
     * @var string
     */
    protected $fromStateId;

    /**
     * @var StateMachineStateEntity|null
     */
    protected $fromStateMachineState;

    /**
     * @var string
     */
    protected $toStateId;

    /**
     * @var StateMachineStateEntity|null
     */
    protected $toStateMachineState;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getStateMachineId(): string
    {
        return $this->stateMachineId;
    }

    public function setStateMachineId(string $stateMachineId): void
    {
        $this->stateMachineId = $stateMachineId;
    }

    public function getStateMachine(): ?StateMachineStateEntity
    {
        return $this->stateMachine;
    }

    public function setStateMachine(StateMachineStateEntity $stateMachine): void
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

    public function getFromStateMachineState(): ?StateMachineStateEntity
    {
        return $this->fromStateMachineState;
    }

    public function setFromStateMachineState(StateMachineStateEntity $fromStateMachineState): void
    {
        $this->fromStateMachineState = $fromStateMachineState;
    }

    public function getToStateId(): string
    {
        return $this->toStateId;
    }

    public function setToStateId(string $toStateId): void
    {
        $this->toStateId = $toStateId;
    }

    public function getToStateMachineState(): ?StateMachineStateEntity
    {
        return $this->toStateMachineState;
    }

    public function setToStateMachineState(StateMachineStateEntity $toStateMachineState): void
    {
        $this->toStateMachineState = $toStateMachineState;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
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
