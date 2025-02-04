<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\User\UserEntity;

#[Package('checkout')]
class StateMachineHistoryEntity extends Entity
{
    use EntityIdTrait;

    protected string $stateMachineId;

    protected ?StateMachineEntity $stateMachine = null;

    protected string $entityName;

    protected string $referencedId;

    protected string $referencedVersionId;

    protected string $fromStateId;

    protected ?StateMachineStateEntity $fromStateMachineState = null;

    protected string $toStateId;

    protected ?StateMachineStateEntity $toStateMachineState = null;

    protected string $userId;

    protected ?UserEntity $user = null;

    protected string $transitionActionName;

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

    public function getReferencedId(): string
    {
        return $this->referencedId;
    }

    public function setReferencedId(string $referencedId): void
    {
        $this->referencedId = $referencedId;
    }

    public function getReferencedVersionId(): string
    {
        return $this->referencedVersionId;
    }

    public function setReferencedVersionId(string $referencedVersionId): void
    {
        $this->referencedVersionId = $referencedVersionId;
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
