<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<StateMachineTranslationEntity>
 */
class StateMachineTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
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
