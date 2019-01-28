<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterCollection;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductStreamEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var array|null
     */
    protected $filter;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var ProductStreamFilterCollection|null
     */
    protected $filters;

    /**
     * @var bool
     */
    protected $invalid;

    /**
     * @var ProductStreamTranslationCollection|null
     */
    protected $translations;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getFilter(): ?array
    {
        return $this->filter;
    }

    public function setFilter(?array $filter): void
    {
        $this->filter = $filter;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getFilters(): ?ProductStreamFilterCollection
    {
        return $this->filters;
    }

    public function setFilters(ProductStreamFilterCollection $filters): void
    {
        $this->filters = $filters;
    }

    public function isInvalid(): bool
    {
        return $this->invalid;
    }

    public function setInvalid(bool $invalid): void
    {
        $this->invalid = $invalid;
    }

    public function getTranslations(): ?ProductStreamTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(?ProductStreamTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
