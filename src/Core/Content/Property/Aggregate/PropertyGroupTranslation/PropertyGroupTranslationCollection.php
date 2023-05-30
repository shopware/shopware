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
     * @return list<string>
     */
    public function getPropertyGroupIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->fmap(fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getPropertyGroupId());

        return $ids;
    }

    public function filterByPropertyGroupId(string $id): self
    {
        return $this->filter(fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getPropertyGroupId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getLanguageIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->fmap(fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getLanguageId());

        return $ids;
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
