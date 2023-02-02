<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<StateMachineStateTranslationEntity>
 */
class StateMachineStateTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
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

    public function getApiAlias(): string
    {
        return 'state_machine_state_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineStateEntity::class;
    }
}
