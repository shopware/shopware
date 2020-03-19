<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation;

use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class PropertyGroupTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $propertyGroupId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var int|null
     */
    protected $position;

    /**
     * @var PropertyGroupEntity|null
     */
    protected $propertyGroup;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getPropertyGroupId(): string
    {
        return $this->propertyGroupId;
    }

    public function setPropertyGroupId(string $propertyGroupId): void
    {
        $this->propertyGroupId = $propertyGroupId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPropertyGroup(): ?PropertyGroupEntity
    {
        return $this->propertyGroup;
    }

    public function setPropertyGroup(PropertyGroupEntity $propertyGroup): void
    {
        $this->propertyGroup = $propertyGroup;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getApiAlias(): string
    {
        return 'product_group_translation';
    }
}
