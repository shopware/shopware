<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class PropertyGroupEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string
     */
    protected $displayType;

    /**
     * @var string
     */
    protected $sortingType;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var int|null
     */
    protected $position;

    /**
     * @var bool
     */
    protected $filterable;

    /**
     * @var bool|null
     */
    protected $visibleOnProductDetailPage;

    /**
     * @var PropertyGroupOptionCollection|null
     */
    protected $options;

    /**
     * @var PropertyGroupTranslationCollection|null
     */
    protected $translations;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getFilterable(): bool
    {
        return $this->filterable;
    }

    public function setFilterable(bool $filterable): void
    {
        $this->filterable = $filterable;
    }

    public function getVisibleOnProductDetailPage(): bool
    {
        return $this->visibleOnProductDetailPage ?? false;
    }

    public function setVisibleOnProductDetailPage(bool $visibleOnProductDetailPage): void
    {
        $this->visibleOnProductDetailPage = $visibleOnProductDetailPage;
    }

    public function getOptions(): ?PropertyGroupOptionCollection
    {
        return $this->options;
    }

    public function setOptions(PropertyGroupOptionCollection $options): void
    {
        $this->options = $options;
    }

    public function getTranslations(): ?PropertyGroupTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(PropertyGroupTranslationCollection $translations): void
    {
        $this->translations = $translations;
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

    public function getDisplayType(): string
    {
        return $this->displayType;
    }

    public function setDisplayType(string $displayType): void
    {
        $this->displayType = $displayType;
    }

    public function getSortingType(): string
    {
        return $this->sortingType;
    }

    public function setSortingType(string $sortingType): void
    {
        $this->sortingType = $sortingType;
    }
}
