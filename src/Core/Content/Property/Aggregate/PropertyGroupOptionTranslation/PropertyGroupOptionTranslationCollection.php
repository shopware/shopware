<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<PropertyGroupOptionTranslationEntity>
 */
#[Package('inventory')]
class PropertyGroupOptionTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getPropertyGroupOptionIds(): array
    {
        return $this->fmap(fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getPropertyGroupOptionId());
    }

    public function filterByPropertyGroupOptionId(string $id): self
    {
        return $this->filter(fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getPropertyGroupOptionId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'product_group_option_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupOptionTranslationEntity::class;
    }
}
