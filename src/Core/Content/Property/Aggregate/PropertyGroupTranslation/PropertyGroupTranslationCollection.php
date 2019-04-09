<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                add(PropertyGroupTranslationEntity $entity)
 * @method void                                set(string $key, PropertyGroupTranslationEntity $entity)
 * @method PropertyGroupTranslationEntity[]    getIterator()
 * @method PropertyGroupTranslationEntity[]    getElements()
 * @method PropertyGroupTranslationEntity|null get(string $key)
 * @method PropertyGroupTranslationEntity|null first()
 * @method PropertyGroupTranslationEntity|null last()
 */
class PropertyGroupTranslationCollection extends EntityCollection
{
    public function getPropertyGroupIds(): array
    {
        return $this->fmap(function (PropertyGroupTranslationEntity $propertyGroupTranslation) {
            return $propertyGroupTranslation->getPropertyGroupId();
        });
    }

    public function filterByPropertyGroupId(string $id): self
    {
        return $this->filter(function (PropertyGroupTranslationEntity $propertyGroupTranslation) use ($id) {
            return $propertyGroupTranslation->getPropertyGroupId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (PropertyGroupTranslationEntity $propertyGroupTranslation) {
            return $propertyGroupTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (PropertyGroupTranslationEntity $propertyGroupTranslation) use ($id) {
            return $propertyGroupTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupTranslationEntity::class;
    }
}
