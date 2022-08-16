<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PropertyGroupTranslationEntity>
 */
class PropertyGroupTranslationCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
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

    /**
     * @return list<string>
     */
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

    public function getApiAlias(): string
    {
        return 'product_group_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupTranslationEntity::class;
    }
}
