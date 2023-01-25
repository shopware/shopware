<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UnitTranslationEntity>
 */
#[Package('inventory')]
class UnitTranslationCollection extends EntityCollection
{
    public function getUnitIds(): array
    {
        return $this->fmap(fn (UnitTranslationEntity $unitTranslation) => $unitTranslation->getUnitId());
    }

    public function filterByUnitId(string $id): self
    {
        return $this->filter(fn (UnitTranslationEntity $unitTranslation) => $unitTranslation->getUnitId() === $id);
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(fn (UnitTranslationEntity $unitTranslation) => $unitTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (UnitTranslationEntity $unitTranslation) => $unitTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'unit_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return UnitTranslationEntity::class;
    }
}
