<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @template TEntityCollection of EntityCollection
 *
 * @template-extends EntitySearchResult<TEntityCollection>
 *
 * @deprecated tag:v6.7.0 - will be removed without replacement use `EntitySearchResult` instead, all methods are now contained in the `EntitySearchResult` and the sorting was not in use any more
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
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            '\Shopware\Storefront\Framework\Page\StorefrontSearchResult will be removed use \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult instead'
        );

        return $this->sortings;
    }

    /**
     * @param array<FieldSorting> $sortings
     */
    public function setSortings(array $sortings): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            '\Shopware\Storefront\Framework\Page\StorefrontSearchResult will be removed use \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult instead'
        );

        $this->sortings = $sortings;
    }

    public function getSorting(): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            '\Shopware\Storefront\Framework\Page\StorefrontSearchResult will be removed use \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult instead'
        );

        return $this->sorting;
    }

    public function setSorting(?string $sorting): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            '\Shopware\Storefront\Framework\Page\StorefrontSearchResult will be removed use \Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult instead'
        );

        $this->sorting = $sorting;
    }
}
