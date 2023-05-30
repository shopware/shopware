<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;

#[Package('storefront')]
class StorefrontSearchResult extends EntitySearchResult
{
    /**
     * @var array
     */
    protected $sortings = [];

    /**
     * @var string|null
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

        return (int) ($this->criteria->getOffset() / $this->criteria->getLimit()) + 1;
    }

    public function getPageCount()
    {
        $total = $this->getTotal();

        //next page mode fetches only the next pages with, not the exact count
        if ($this->getCriteria()->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_NEXT_PAGES) {
            $total += $this->getCriteria()->getOffset();
        }

        return $total / $this->getCriteria()->getLimit();
    }
}
