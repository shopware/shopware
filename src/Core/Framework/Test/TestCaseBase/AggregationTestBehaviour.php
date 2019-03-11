<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;

trait AggregationTestBehaviour
{
    public function setUp(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeUpdate('DELETE FROM tax');
    }

    public function setupFixtures(Context $context): void
    {
        /** @var EntityRepositoryInterface $taxRepository */
        $taxRepository = $this->getContainer()->get('tax.repository');

        $payload = [
            ['name' => 'Tax rate #1', 'taxRate' => 10],
            ['name' => 'Tax rate #2', 'taxRate' => 20],
            ['name' => 'Tax rate #3', 'taxRate' => 10],
            ['name' => 'Tax rate #4', 'taxRate' => 20],
            ['name' => 'Tax rate #5', 'taxRate' => 50],
            ['name' => 'Tax rate #6', 'taxRate' => 50],
            ['name' => 'Tax rate #7', 'taxRate' => 90],
            ['name' => 'Tax rate #8', 'taxRate' => 10],
        ];

        $taxRepository->create($payload, $context);
    }

    public function setupGroupByFixtures(Context $context): void
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        /** @var EntityRepositoryInterface $categoryRepository */
        $categoryRepository = $this->getContainer()->get('category.repository');

        $category1 = Uuid::uuid4()->getHex();
        $category2 = Uuid::uuid4()->getHex();
        $category3 = Uuid::uuid4()->getHex();
        $category4 = Uuid::uuid4()->getHex();
        $categories = [
            ['id' => $category1, 'name' => 'cat1'],
            ['id' => $category2, 'name' => 'cat2'],
            ['id' => $category3, 'name' => 'cat3'],
            ['id' => $category4, 'name' => 'cat4'],
        ];
        $categoryRepository->create($categories, $context);

        $products = [
            [
                'name' => 'product 1',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'manufacturer1'],
                'categories' => [
                    ['id' => $category1],
                    ['id' => $category3],
                ],
            ],
            [
                'name' => 'product 2',
                'price' => ['gross' => 20, 'net' => 19, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'manufacturer2'],
                'categories' => [
                    ['id' => $category1],
                    ['id' => $category2],
                ],
            ],
            [
                'name' => 'product 3',
                'price' => ['gross' => 50, 'net' => 49, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'manufacturer1'],
                'categories' => [
                    ['id' => $category2],
                ],
            ],
            [
                'name' => 'product 4',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'manufacturer2'],
                'categories' => [
                    ['id' => $category1],
                    ['id' => $category4],
                ],
            ],
            [
                'name' => 'product 5',
                'price' => ['gross' => 90, 'net' => 99, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'manufacturer3'],
                'categories' => [
                    ['id' => $category2],
                    ['id' => $category3],
                ],
            ],
            [
                'name' => 'product 6',
                'price' => ['gross' => 50, 'net' => 49, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'manufacturer2'],
                'categories' => [
                    ['id' => $category3],
                ],
            ],
            [
                'name' => 'product 6',
                'price' => ['gross' => 20, 'net' => 19, 'linked' => false],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'manufacturer1'],
                'categories' => [
                    ['id' => $category4],
                ],
            ],
        ];
        $productRepository->create($products, $context);
    }
}
