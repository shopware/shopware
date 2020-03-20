<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                               add(StateMachineTranslationEntity $entity)
 * @method void                               set(string $key, StateMachineTranslationEntity $entity)
 * @method StateMachineTranslationEntity[]    getIterator()
 * @method StateMachineTranslationEntity[]    getElements()
 * @method StateMachineTranslationEntity|null get(string $key)
 * @method StateMachineTranslationEntity|null first()
 * @method StateMachineTranslationEntity|null last()
 */
class StateMachineTranslationCollection extends EntityCollection
{
    public function getLanguageIds(): array
    {
        return $this->fmap(function (StateMachineTranslationEntity $stateMachineTranslation) {
            return $stateMachineTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (StateMachineTranslationEntity $stateMachineTranslation) use ($id) {
            return $stateMachineTranslation->getLanguageId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'state_machine_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineTranslationEntity::class;
    }
}
