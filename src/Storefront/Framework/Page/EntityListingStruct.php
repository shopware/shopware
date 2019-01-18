<?php

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class EntityListingStruct extends EntitySearchResult
{
    /**
     * @var array
     */
    protected $sortings = [];

    /**
     * @var null|string
     */
    protected $sorting;

    public function getSortings(): array
    {
        return $this->sortings;
    }

    public function setSortings(array $sortings): void
    {
        $this->sortings = $sortings;
    }

    public function getSorting(): ?string
    {
        return $this->sorting;
    }

    public function setSorting(?string $sorting): void
    {
        $this->sorting = $sorting;
    }

    public function getPage(): int
    {
        if ($this->criteria->getOffset() === 0) {
            return 1;
        }

        return (int) ($this->criteria->getOffset() / $this->criteria->getLimit());
    }
}