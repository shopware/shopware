<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class ProductCrossSellingAssignedProductsRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDuplicateAssignedProductsAreRejected(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $assignedProduct = (new ProductBuilder($ids, 'assigned-product'))
            ->manufacturer('assigned-manufacturer')
            ->price(15.0, 10.0)
            ->build();

        $productRepository = static::getContainer()->get('product.repository');
        $productRepository->create([$assignedProduct], $context);

        $mainProduct = (new ProductBuilder($ids, 'main-product'))
            ->manufacturer('main-manufacturer')
            ->price(15.0, 10.0)
            ->build();

        $mainProduct['crossSellings'] = [[
            'name' => 'Duplicate cross selling',
            'type' => ProductCrossSellingDefinition::TYPE_PRODUCT_LIST,
            'active' => true,
            'limit' => 24,
            'assignedProducts' => [
                [
                    'productId' => $ids->get('assigned-product'),
                    'position' => 1,
                ],
                [
                    'productId' => $ids->get('assigned-product'),
                    'position' => 2,
                ],
            ],
        ]];

        static::expectException(UniqueConstraintViolationException::class);
        $productRepository->create([$mainProduct], $context);
    }
}
