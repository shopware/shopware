<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute\Translation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class AttributeTranslationCollection extends EntityCollection
{
    public function getAttributeIds(): array
    {
        return $this->fmap(function (AttributeTranslationEntity $attributeTranslation) {
            return $attributeTranslation->getAttributeId();
        });
    }

    public function filterByAttributeId(string $id): self
    {
        return $this->filter(function (AttributeTranslationEntity $attributeTranslation) use ($id) {
            return $attributeTranslation->getAttributeId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (AttributeTranslationEntity $attributeTranslation) {
            return $attributeTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (AttributeTranslationEntity $attributeTranslation) use ($id) {
            return $attributeTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return AttributeTranslationEntity::class;
    }
}
