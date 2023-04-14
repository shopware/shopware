<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Sorting;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Exception\DuplicateProductSortingKeyException;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ProductListingSortingTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $productSortingRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productSortingRepository = $this->getContainer()->get('product_sorting.repository');
    }

    public function testProductSortingFieldPriority(): void
    {
        $productSortingEntity = new ProductSortingEntity();
        $productSortingEntity->setFields(
            [
                ['field' => 'product.name', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => 1],
                ['field' => 'product.cheapestPrice', 'order' => 'asc', 'priority' => 1000, 'naturalSorting' => 1],
            ]
        );

        /** @var FieldSorting[] $sortings */
        $sortings = $productSortingEntity->createDalSorting();

        static::assertCount(2, $sortings);
        static::assertEquals('product.cheapestPrice', $sortings[0]->getField());
        static::assertEquals('product.name', $sortings[1]->getField());
    }

    public function testDuplicateProductSortingKey(): void
    {
        $productSortingKey = Uuid::randomHex();

        $data = [
            'id' => Uuid::randomHex(),
            'key' => $productSortingKey,
            'priority' => 0,
            'active' => true,
            'fields' => [
                ['field' => 'product.name', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => 1],
            ],
            'label' => 'test',
        ];

        $this->productSortingRepository->create([$data], Context::createDefaultContext());

        $data = [
            'id' => Uuid::randomHex(),
            'key' => $productSortingKey,
            'name' => 'product',
            'priority' => 0,
            'active' => true,
            'fields' => [
                ['field' => 'product.name', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => 1],
            ],
            'label' => 'test',
        ];

        $this->expectException(DuplicateProductSortingKeyException::class);
        $this->expectExceptionMessage('Sorting with key "' . $productSortingKey . '" already exists.');

        $this->productSortingRepository->create([$data], Context::createDefaultContext());
    }
}
