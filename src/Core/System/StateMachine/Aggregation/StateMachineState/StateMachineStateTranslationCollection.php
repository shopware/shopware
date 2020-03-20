<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                    add(StateMachineStateTranslationEntity $entity)
 * @method void                                    set(string $key, StateMachineStateTranslationEntity $entity)
 * @method StateMachineStateTranslationEntity[]    getIterator()
 * @method StateMachineStateTranslationEntity[]    getElements()
 * @method StateMachineStateTranslationEntity|null get(string $key)
 * @method StateMachineStateTranslationEntity|null first()
 * @method StateMachineStateTranslationEntity|null last()
 */
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

    public function getApiAlias(): string
    {
        return 'state_machine_state_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineStateEntity::class;
    }
}
