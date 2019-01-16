<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class StateMachineStateTranslationCollection extends EntityCollection
{
    public function getLanguageIds(): array
    {
        return $this->fmap(function (StateMachineStateTranslationEntity $stateMachineStateTranslation) {
            return $stateMachineStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (StateMachineStateTranslationEntity $stateMachineStateTranslation) use ($id) {
            return $stateMachineStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return StateMachineStateEntity::class;
    }
}
