<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @extends EntitySearchResult<ProductCollection>
 */
#[Package('inventory')]
#[ClassHierarchyChange(version: 'v6.8.0', description: 'Will no longer extend EntitySearchResult, but will keep extending Struct.', newParentClass: Struct::class)]
class ProductListingResult extends EntitySearchResult
{
    protected ?string $sorting = null;

    /**
     * @var array<string, int|float|string|bool|array<mixed>|null>
     */
    protected array $currentFilters = [];

    protected ProductSortingCollection $availableSortings;

    protected ?string $streamId = null;

    /**
     * Construction entry point with a stable signature across the v6.8.0 cut. Callers that adopt this method now will keep working after the structural change.
     *
     * @param EntitySearchResult<ProductCollection> $result
     * @param array<string, int|float|string|bool|array<mixed>|null> $currentFilters
     */
    public static function fromSearchResult(
        EntitySearchResult $result,
        ?ProductSortingCollection $availableSortings = null,
        ?string $sorting = null,
        array $currentFilters = [],
        ?string $streamId = null,
    ): self {
        $instance = self::createFrom($result);

        if ($availableSortings !== null) {
            $instance->availableSortings = $availableSortings;
        }
        $instance->sorting = $sorting;
        $instance->currentFilters = $currentFilters;
        $instance->streamId = $streamId;

        return $instance;
    }

    /**
     * @param int|float|string|bool|array<mixed>|null $value
     */
    public function addCurrentFilter(string $key, $value): void
    {
        $this->currentFilters[$key] = $value;
    }

    public function getAvailableSortings(): ProductSortingCollection
    {
        return $this->availableSortings;
    }

    public function setAvailableSortings(ProductSortingCollection $availableSortings): void
    {
        $this->availableSortings = $availableSortings;
    }

    public function getSorting(): ?string
    {
        return $this->sorting;
    }

    public function setSorting(?string $sorting): void
    {
        $this->sorting = $sorting;
    }

    /**
     * @return array<string, int|float|string|bool|array<mixed>|null>
     */
    public function getCurrentFilters(): array
    {
        return $this->currentFilters;
    }

    /**
     * @return int|float|string|bool|array<mixed>|null
     */
    public function getCurrentFilter(string $key)
    {
        return $this->currentFilters[$key] ?? null;
    }

    public function getApiAlias(): string
    {
        return 'product_listing';
    }

    public function setStreamId(?string $streamId): void
    {
        $this->streamId = $streamId;
    }

    public function getStreamId(): ?string
    {
        return $this->streamId;
    }
}
