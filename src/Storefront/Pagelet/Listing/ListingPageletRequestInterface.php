<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing;

interface ListingPageletRequestInterface
{
    public function getPage(): int;

    public function setPage(int $page): void;

    public function getLimit(): int;

    public function setLimit(int $limit): void;

    public function getSortingKey(): ?string;

    public function setSortingKey(?string $sortingKey): void;

    public function getNavigationId(): string;

    public function setNavigationId(string $navigationId): void;

    public function getManufacturerNames(): array;

    public function setManufacturerNames(array $manufacturerNames): void;

    public function getDatasheetIds(): array;

    public function setDatasheetIds(array $datasheetIds): void;

    public function loadAggregations(): bool;

    public function setLoadAggregations(bool $loadAggregations): void;

    public function getMinPrice(): ?float;

    public function setMinPrice(?float $minPrice): void;

    public function getMaxPrice(): ?float;

    public function setMaxPrice(?float $maxPrice): void;
}
