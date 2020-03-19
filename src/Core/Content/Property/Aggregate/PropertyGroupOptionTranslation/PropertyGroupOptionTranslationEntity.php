<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;

class PropertyGroupOptionTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $propertyGroupOptionId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var int|null
     */
    protected $position;

    /**
     * @var PropertyGroupOptionEntity|null
     */
    protected $propertyGroupOption;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getPropertyGroupOptionId(): string
    {
        return $this->propertyGroupOptionId;
    }

    public function setPropertyGroupOptionId(string $propertyGroupOptionId): void
    {
        $this->propertyGroupOptionId = $propertyGroupOptionId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPropertyGroupOption(): ?PropertyGroupOptionEntity
    {
        return $this->propertyGroupOption;
    }

    public function setPropertyGroupOption(PropertyGroupOptionEntity $propertyGroupOption): void
    {
        $this->propertyGroupOption = $propertyGroupOption;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
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
        return 'product_group_option_translation';
    }
}
