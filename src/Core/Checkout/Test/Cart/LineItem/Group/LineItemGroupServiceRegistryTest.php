<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupServiceRegistry;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupCountPackager;
use Shopware\Core\Checkout\Cart\LineItem\Group\Packager\LineItemGroupUnitPriceNetPackager;
use Shopware\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceAscSorter;
use Shopware\Core\Checkout\Cart\LineItem\Group\Sorter\LineItemGroupPriceDescSorter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class LineItemGroupServiceRegistryTest extends TestCase
{
    /**
     * This test verifies that our packagers are
     * correctly registered in our registry.
     *
     * @group lineitemgroup
     */
    public function testPackagersAreRegistered(): void
    {
        $packagers = [
            new LineItemGroupCountPackager(),
            new LineItemGroupUnitPriceNetPackager(),
        ];
        $sorters = [];

        $registry = new LineItemGroupServiceRegistry($packagers, $sorters);

        static::assertCount(2, $registry->getPackagers());
    }

    /**
     * This test verifies that our sorters are
     * correctly registered in our registry.
     *
     * @group lineitemgroup
     */
    public function testSortersAreRegistered(): void
    {
        $packagers = [];
        $sorters = [
            new LineItemGroupPriceAscSorter(),
        ];

        $registry = new LineItemGroupServiceRegistry($packagers, $sorters);

        static::assertCount(1, $registry->getSorters());
    }

    /**
     * This test verifies that we can retrieve
     * our packager by its key.
     *
     * @group lineitemgroup
     */
    public function testGetPackagerByKey(): void
    {
        $packager = new LineItemGroupCountPackager();

        $packagers = [
            $packager,
            new LineItemGroupUnitPriceNetPackager(),
        ];
        $sorters = [];

        $registry = new LineItemGroupServiceRegistry($packagers, $sorters);

        static::assertSame($packager, $registry->getPackager($packager->getKey()));
    }

    /**
     * This test verifies that we can retrieve
     * our sorter by its key.
     *
     * @group lineitemgroup
     */
    public function testGetSorterByKey(): void
    {
        $sorter = new LineItemGroupPriceAscSorter();

        $packagers = [];
        $sorters = [
            $sorter,
            new LineItemGroupPriceDescSorter(),
        ];

        $registry = new LineItemGroupServiceRegistry($packagers, $sorters);

        static::assertSame($sorter, $registry->getSorter($sorter->getKey()));
    }
}
