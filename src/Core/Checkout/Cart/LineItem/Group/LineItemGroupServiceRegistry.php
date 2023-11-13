<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class LineItemGroupServiceRegistry
{
    /**
     * @internal
     */
    public function __construct(
        private readonly iterable $packagers,
        private readonly iterable $sorters
    ) {
    }

    /**
     * Gets a list of all registered packagers.
     */
    public function getPackagers(): \Generator
    {
        foreach ($this->packagers as $packager) {
            yield $packager;
        }
    }

    /**
     * Gets the packager for the provided key, if registered.
     *
     * @throws LineItemGroupPackagerNotFoundException
     */
    public function getPackager(string $key): LineItemGroupPackagerInterface
    {
        /** @var LineItemGroupPackagerInterface $packager */
        foreach ($this->packagers as $packager) {
            if (mb_strtolower($packager->getKey()) === mb_strtolower($key)) {
                return $packager;
            }
        }

        throw new LineItemGroupPackagerNotFoundException($key);
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
     * @throws LineItemGroupSorterNotFoundException
     */
    public function getSorter(string $key): LineItemGroupSorterInterface
    {
        /** @var LineItemGroupSorterInterface $sorter */
        foreach ($this->sorters as $sorter) {
            if (mb_strtolower($sorter->getKey()) === mb_strtolower($key)) {
                return $sorter;
            }
        }

        throw new LineItemGroupSorterNotFoundException($key);
    }
}
