<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Exception\FilterPickerNotFoundException;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Exception\FilterSorterNotFoundException;

class FilterServiceRegistry
{
    /**
     * @var iterable
     */
    private $sorters;

    /**
     * @var iterable
     */
    private $pickers;

    public function __construct(iterable $sorters, iterable $pickers)
    {
        $this->sorters = $sorters;
        $this->pickers = $pickers;
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

    /**
     * Gets a list of all registered sorters.
     */
    public function getPickers(): \Generator
    {
        foreach ($this->pickers as $picker) {
            yield $picker;
        }
    }

    /**
     * Gets the picker for the provided key, if registered.
     *
     * @throws FilterPickerNotFoundException
     */
    public function getPicker(string $key): FilterPickerInterface
    {
        foreach ($this->pickers as $picker) {
            if (mb_strtolower($picker->getKey()) === mb_strtolower($key)) {
                return $picker;
            }
        }

        throw new FilterPickerNotFoundException($key);
    }
}
