<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute\Translation;

use Shopware\Core\Framework\Attribute\AttributeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class AttributeTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $attributeId;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var AttributeEntity
     */
    protected $attribute;

    public function getAttributeId(): string
    {
        return $this->attributeId;
    }

    public function setAttributeId(string $attributeId): void
    {
        $this->attributeId = $attributeId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getAttribute(): AttributeEntity
    {
        return $this->attribute;
    }

    public function setAttribute(AttributeEntity $attribute): void
    {
        $this->attribute = $attribute;
    }
}
