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
     * @return list<string>
     */
    public function getPropertyGroupOptionIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->fmap(fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getPropertyGroupOptionId());

        return $ids;
    }

    public function filterByPropertyGroupOptionId(string $id): self
    {
        return $this->filter(fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getPropertyGroupOptionId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->fmap(fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getLanguageId());

        return $ids;
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
