<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                       add(UnitTranslationEntity $entity)
 * @method void                       set(string $key, UnitTranslationEntity $entity)
 * @method UnitTranslationEntity[]    getIterator()
 * @method UnitTranslationEntity[]    getElements()
 * @method UnitTranslationEntity|null get(string $key)
 * @method UnitTranslationEntity|null first()
 * @method UnitTranslationEntity|null last()
 */
class UnitTranslationCollection extends EntityCollection
{
    public function getUnitIds(): array
    {
        return $this->fmap(function (UnitTranslationEntity $unitTranslation) {
            return $unitTranslation->getUnitId();
        });
    }

    public function filterByUnitId(string $id): self
    {
        return $this->filter(function (UnitTranslationEntity $unitTranslation) use ($id) {
            return $unitTranslation->getUnitId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (UnitTranslationEntity $unitTranslation) {
            return $unitTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (UnitTranslationEntity $unitTranslation) use ($id) {
            return $unitTranslation->getLanguageId() === $id;
        });
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
