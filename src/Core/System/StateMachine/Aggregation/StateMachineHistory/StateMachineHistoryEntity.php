<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\User\UserEntity;

class StateMachineHistoryEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $stateMachineId;

    /**
     * @var StateMachineEntity|null
     */
    protected $stateMachine;

    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var array
     */
    protected $entityId;

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
     * @var string
     */
    protected $userId;

    /**
     * @var UserEntity|null
     */
    protected $user;

    /**
     * @var string
     */
    protected $transitionActionName;

    public function getTransitionActionName(): string
    {
        return $this->transitionActionName;
    }

    public function setTransitionActionName(string $transitionActionName): void
    {
        $this->transitionActionName = $transitionActionName;
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

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function getEntityId(): array
    {
        return $this->entityId;
    }

    public function setEntityId(array $entityId): void
    {
        $this->entityId = $entityId;
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

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->user = $user;
    }

    public function setFromStateMachineState(StateMachineStateEntity $fromStateMachineState): void
    {
        $this->fromStateMachineState = $fromStateMachineState;
    }
}
