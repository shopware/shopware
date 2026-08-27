<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class Transition
{
    /**
     * @param list<string> $skipIfInStates Technical names of states the transition is skipped from, checked
     *                                     against the state that is current when the transition runs
     */
    public function __construct(
        private readonly string $entityName,
        private readonly string $entityId,
        private readonly string $transitionName,
        private readonly string $stateFieldName,
        private readonly ?string $internalComment = null,
        private readonly array $skipIfInStates = [],
    ) {
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getTransitionName(): string
    {
        return $this->transitionName;
    }

    public function getStateFieldName(): string
    {
        return $this->stateFieldName;
    }

    public function getInternalComment(): ?string
    {
        return $this->internalComment;
    }

    /**
     * @return list<string>
     */
    public function getSkipIfInStates(): array
    {
        return $this->skipIfInStates;
    }
}
