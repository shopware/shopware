<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Exception\FilterSorterNotFoundException;

class FilterServiceRegistry
{
    /**
     * @var iterable
     */
    private $sorters;

    public function __construct(iterable $sorters)
    {
        $this->sorters = $sorters;
    }

    /**
     * Gets a list of all registered sorters.
     */
    public function getSorters(): \Generator
    {
        foreach ($this->sorters as $sorter) {
            yield $sorter;
        }
    }

    /**
     * Gets the sorter for the provided key, if registered.
     *
     * @throws FilterSorterNotFoundException
     */
    public function getSorter(string $key): FilterSorterInterface
    {
        /** @var FilterSorterInterface $sorter */
        foreach ($this->sorters as $sorter) {
            if (mb_strtolower($sorter->getKey()) === mb_strtolower($key)) {
                return $sorter;
            }
        }

        throw new FilterSorterNotFoundException($key);
    }
}
