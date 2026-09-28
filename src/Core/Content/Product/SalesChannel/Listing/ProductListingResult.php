<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
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
     * @deprecated tag:v6.8.0 - Will be removed. Use createFrom() and the setters instead.
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
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __FUNCTION__, 'v6.8.0.0', 'createFrom()'));

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
