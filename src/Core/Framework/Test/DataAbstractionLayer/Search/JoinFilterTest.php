<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class JoinFilterTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @beforeClass
     */
    public static function startTransactionBefore(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->beginTransaction();
    }

    /**
     * @afterClass
     */
    public static function stopTransactionAfter(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->rollBack();
    }

    public function testIndexing()
    {
        $this->getContainer()->get(Connection::class)
            ->executeUpdate('DELETE FROM product');

        $ids = new IdsCollection();

        $products = [
            [
                'id' => $ids->get('product-1'),
                'name' => 'Matching product',
                'productNumber' => $ids->get('product-1'),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'tax' => ['id' => $ids->get('tax'), 'name' => 'test', 'taxRate' => 15],
                'manufacturer' => [
                    'id' => $ids->get('manufacturer-1'),
                    'name' => 'Test manufacturer match',
                ],
                'properties' => [
                    ['id' => $ids->get('red'), 'name' => 'red', 'group' => ['id' => $ids->get('color'), 'name' => 'color']],
                    ['id' => $ids->get('yellow'), 'name' => 'yellow', 'group' => ['id' => $ids->get('color'), 'name' => 'color']],
                    ['id' => $ids->get('XL'), 'name' => 'XL', 'group' => ['id' => $ids->get('size'), 'name' => 'size']],
                    ['id' => $ids->get('L'), 'name' => 'L', 'group' => ['id' => $ids->get('size'), 'name' => 'size']],
                ],
                'categories' => [
                    ['id' => $ids->get('category-1'), 'name' => 'test'],
                    ['id' => $ids->get('category-2'), 'name' => 'test'],
                ],
            ],
            [
                'id' => $ids->get('product-2'),
                'name' => 'Product',
                'productNumber' => $ids->get('product-2'),
                'stock' => 10,
                'manufacturer' => [
                    'id' => $ids->get('manufacturer-2'),
                    'name' => 'Test manufacturer match',
                ],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'taxId' => $ids->get('tax'),
                'properties' => [
                    ['id' => $ids->get('red'), 'name' => 'red', 'groupId' => $ids->get('color')],
                ],
                'categories' => [
                    ['id' => $ids->get('category-1'), 'name' => 'test'],
                    ['id' => $ids->get('category-3'), 'name' => 'test'],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, $ids->getContext());

        $result = $this->getContainer()->get('product.repository')
            ->searchIds(new Criteria($ids->getList(['product-1', 'product-2'])), $ids->getContext());

        static::assertEquals(2, $result->getTotal());

        return $ids;
    }

    /**
     * @depends testIndexing
     */
    public function testEqualsFilterManyToManyIdField(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->getList(['product-1', 'product-2']));
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', $ids->get('red'))
        );
        $criteria->addFilter(
            new EqualsFilter('product.properties.id', $ids->get('yellow'))
        );

        $result = $this->getContainer()->get('product.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(1, $result->getTotal());
        static::assertFalse($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-1')));
    }

    /**
     * @depends testIndexing
     */
    public function testNestedManyToManyIdField(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->getList(['category-1', 'category-2', 'category-3']));

        $criteria->addFilter(
            new EqualsAnyFilter('category.products.properties.id', [$ids->get('red'), $ids->get('yellow')])
        );
        $criteria->addFilter(
            new EqualsAnyFilter('category.products.properties.id', [$ids->get('XL'), $ids->get('L')])
        );

        $result = $this->getContainer()->get('category.repository')
            ->searchIds($criteria, $ids->getContext());

        static::assertEquals(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('category-1')));
        static::assertTrue($result->has($ids->get('category-2')));
        static::assertFalse($result->has($ids->get('category-3')));
    }
}
