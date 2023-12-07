<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

/**
 * @template TEntityCollection of EntityCollection
 *
 * @template-extends EntitySearchResult<TEntityCollection>
 */
#[Package('storefront')]
class StorefrontSearchResult extends EntitySearchResult
{
    /**
     * @var array<FieldSorting>
     */
    protected $sortings = [];

    /**
     * @var string|null
     */
    protected $sorting;

    /**
     * @return array<FieldSorting>
     */
    public function getSortings(): array
    {
        return $this->sortings;
    }

    /**
     * @param array<FieldSorting> $sortings
     */
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
}
