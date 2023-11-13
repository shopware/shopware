<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<StateMachineTranslationEntity>
 */
#[Package('core')]
class StateMachineTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (StateMachineTranslationEntity $stateMachineTranslation) => $stateMachineTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (StateMachineTranslationEntity $stateMachineTranslation) => $stateMachineTranslation->getLanguageId() === $id);
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
