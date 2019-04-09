<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                      add(PropertyGroupOptionTranslationEntity $entity)
 * @method void                                      set(string $key, PropertyGroupOptionTranslationEntity $entity)
 * @method PropertyGroupOptionTranslationEntity[]    getIterator()
 * @method PropertyGroupOptionTranslationEntity[]    getElements()
 * @method PropertyGroupOptionTranslationEntity|null get(string $key)
 * @method PropertyGroupOptionTranslationEntity|null first()
 * @method PropertyGroupOptionTranslationEntity|null last()
 */
class PropertyGroupOptionTranslationCollection extends EntityCollection
{
    public function getPropertyGroupOptionIds(): array
    {
        return $this->fmap(function (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) {
            return $propertyGroupOptionTranslation->getPropertyGroupOptionId();
        });
    }

    public function filterByPropertyGroupOptionId(string $id): self
    {
        return $this->filter(function (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) use ($id) {
            return $propertyGroupOptionTranslation->getPropertyGroupOptionId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) {
            return $propertyGroupOptionTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) use ($id) {
            return $propertyGroupOptionTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupOptionTranslationEntity::class;
    }
}
