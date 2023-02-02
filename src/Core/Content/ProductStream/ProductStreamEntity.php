<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\ProductExport\ProductExportCollection;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterCollection;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class ProductStreamEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

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
    protected $apiFilter;

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

    /**
     * @var ProductExportCollection|null
     */
    protected $productExports;

    /**
     * @var ProductCrossSellingCollection|null
     */
    protected $productCrossSellings;

    /**
     * @var CategoryCollection|null
     */
    protected $categories;

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

    public function getApiFilter(): ?array
    {
        return $this->apiFilter;
    }

    public function setApiFilter(?array $apiFilter): void
    {
        $this->apiFilter = $apiFilter;
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

    public function setTranslations(ProductStreamTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getProductExports(): ?ProductExportCollection
    {
        return $this->productExports;
    }

    public function setProductExports(ProductExportCollection $productExports): void
    {
        $this->productExports = $productExports;
    }

    public function getProductCrossSellings(): ?ProductCrossSellingCollection
    {
        return $this->productCrossSellings;
    }

    public function setProductCrossSellings(ProductCrossSellingCollection $productCrossSellings): void
    {
        $this->productCrossSellings = $productCrossSellings;
    }

    public function getCategories(): ?CategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryCollection $categories): void
    {
        $this->categories = $categories;
    }
}
