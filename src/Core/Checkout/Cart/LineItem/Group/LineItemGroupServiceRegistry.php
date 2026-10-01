<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class LineItemGroupServiceRegistry
{
    /**
     * @internal
     *
     * @param iterable<LineItemGroupPackagerInterface> $packagers
     * @param iterable<LineItemGroupSorterInterface> $sorters
     */
    public function __construct(
        private readonly iterable $packagers,
        private readonly iterable $sorters
    ) {
    }

    /**
     * Gets a list of all registered packagers.
     *
     * @return \Generator<LineItemGroupPackagerInterface>
     */
    public function getPackagers(): \Generator
    {
        yield from $this->packagers;
    }

    /**
     * Gets the packager for the provided key, if registered.
     *
     * @throws CartException
     */
    public function getPackager(string $key): LineItemGroupPackagerInterface
    {
        foreach ($this->packagers as $packager) {
            if (mb_strtolower($packager->getKey()) === mb_strtolower($key)) {
                return $packager;
            }
        }

        throw CartException::lineItemGroupPackagerNotFoundException($key);
    }

    /**
     * Gets a list of all registered sorters.
     */
    /**
     * @return \Generator<LineItemGroupSorterInterface>
     */
    public function getSorters(): \Generator
    {
        yield from $this->sorters;
    }

    /**
     * Gets the sorter for the provided key, if registered.
     *
     * @throws CartException
     */
    public function getSorter(string $key): LineItemGroupSorterInterface
    {
        foreach ($this->sorters as $sorter) {
            if (mb_strtolower($sorter->getKey()) === mb_strtolower($key)) {
                return $sorter;
            }
        }

        throw CartException::lineItemGroupSorterNotFoundException($key);
    }
}
