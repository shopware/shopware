<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate;

use Shopware\Core\Framework\Context;

/**
 * @internal In order to manipulate the filter process, provide your own tagged AbstractStockUpdateFilter
 *
 * @package core
 */
final class StockUpdateFilterProvider
{
    /**
     * @internal
     *
     * @param AbstractStockUpdateFilter[] $filters
     */
    public function __construct(private readonly iterable $filters)
    {
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    public function filterProductIdsForStockUpdates(array $ids, Context $context): array
    {
        foreach ($this->filters as $filter) {
            $ids = $filter->filter($ids, $context);
        }

        return $ids;
    }
}
