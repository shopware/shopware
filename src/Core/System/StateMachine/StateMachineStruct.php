<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateStruct;

class StateMachineStruct extends Entity
{
    /**
     * @var string
     */
    protected $technicalName;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var Collection|null
     */
    protected $transitions;

    /**
     * @var Collection|null
     */
    protected $states;

    /**
     * @var StateMachineStateStruct|null
     */
    protected $initialState;

    /**
     * @var string|null
     */
    protected $initialStateId;

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

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTransitions(): ?Collection
    {
        return $this->transitions;
    }

    public function setTransitions(Collection $transitions): void
    {
        $this->transitions = $transitions;
    }

    public function getStates(): ?Collection
    {
        return $this->states;
    }

    public function setStates(Collection $states): void
    {
        $this->states = $states;
    }

    public function getInitialState(): StateMachineStateStruct
    {
        return $this->initialState;
    }

    public function setInitialState(StateMachineStateStruct $initialState): void
    {
        $this->initialState = $initialState;
    }

    public function getInitialStateId(): ?string
    {
        return $this->initialStateId;
    }

    public function setInitialStateId(string $initialStateId): void
    {
        $this->initialStateId = $initialStateId;
    }

    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function setTranslations(Collection $translations): void
    {
        $this->translations = $translations;
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
}
