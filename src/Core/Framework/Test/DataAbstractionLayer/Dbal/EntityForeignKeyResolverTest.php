<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityForeignKeyResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $testConnection;

    /**
     * @var EntityForeignKeyResolver
     */
    private $entityForeignKeyResolver;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function testItCreatesEventsForWriteProtectedCascadeDeletes(): void
    {
        $categoryIds = [
            'parentCategory' => Uuid::randomHex(),
            'childCategory' => Uuid::randomHex(),
            'secondRootCategory' => Uuid::randomHex(),
        ];

        $productId = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $context = Context::createDefaultContext();

        $productRepository->create([
            [
                'id' => $productId,
                'name' => 'produt to delete',
                'productNumber' => 'sw-test-1',
                'price' => [
                    [
                        'gross' => 200,
                        'net' => 190,
                        'linked' => true,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
                'stock' => 100,
                'tax' => [
                    'name' => 'testTax',
                    'taxRate' => 10,
                ],
                'categories' => [
                    [
                        'id' => $categoryIds['parentCategory'],
                        'name' => 'parent category',
                    ],
                    [
                        'id' => $categoryIds['childCategory'],
                        'name' => 'child category',
                        'parentId' => $categoryIds['parentCategory'],
                    ],
                    [
                        'id' => $categoryIds['secondRootCategory'],
                        'name' => 'second root',
                    ],
                ],
            ],
        ], $context);

        $deletedEvent = $productRepository->delete([['id' => $productId]], $context);

        $deletedProduct = $deletedEvent->getPrimaryKeys('product');
        $deletedCategories = $deletedEvent->getPrimaryKeys('category');
        $deletedCategoriesRo = $deletedEvent->getPrimaryKeys('product_category_tree');

        static::assertEquals($productId, $deletedProduct[0]);
        static::assertEmpty($deletedCategories);
        static::assertCount(3, $deletedCategoriesRo);

        foreach ($deletedCategoriesRo as $deletedRo) {
            foreach ($categoryIds as $index => $id) {
                if ($id === $deletedRo['categoryId']) {
                    unset($categoryIds[$index]);
                }
            }
        }

        static::assertEmpty($categoryIds);
    }
}
