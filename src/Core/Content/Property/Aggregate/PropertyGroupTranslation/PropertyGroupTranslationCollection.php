<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PropertyGroupTranslationEntity>
 */
#[Package('inventory')]
class PropertyGroupTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getPropertyGroupIds(): array
    {
        return $this->fmap(fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getPropertyGroupId());
    }

    public function filterByPropertyGroupId(string $id): self
    {
        return $this->filter(fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getPropertyGroupId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getLanguageId() === $id);
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
