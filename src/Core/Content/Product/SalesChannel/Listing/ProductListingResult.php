<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will no longer extend EntitySearchResult.
 *
 * @extends EntitySearchResult<ProductCollection>
 */
#[Package('inventory')]
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
     * @param int|float|string|bool|array<mixed>|null $value
     */
    public function addCurrentFilter(string $key, $value): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->currentFilters[$key] = $value;
    }

    public function getAvailableSortings(): ProductSortingCollection
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->availableSortings;
    }

    public function setAvailableSortings(ProductSortingCollection $availableSortings): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->availableSortings = $availableSortings;
    }

    public function getSorting(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->sorting;
    }

    public function setSorting(?string $sorting): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->sorting = $sorting;
    }

    /**
     * @return array<string, int|float|string|bool|array<mixed>|null>
     */
    public function getCurrentFilters(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->currentFilters;
    }

    /**
     * @return int|float|string|bool|array<mixed>|null
     */
    public function getCurrentFilter(string $key)
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->currentFilters[$key] ?? null;
    }

    public function getApiAlias(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return 'product_listing';
    }

    public function setStreamId(?string $streamId): void
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        $this->streamId = $streamId;
    }

    public function getStreamId(): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', \sprintf('Class "%s" is deprecated for v6.8.0.0.', self::class));

        return $this->streamId;
    }
}
