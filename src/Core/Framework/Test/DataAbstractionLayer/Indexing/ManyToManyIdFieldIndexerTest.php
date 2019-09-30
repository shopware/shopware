<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ManyToManyIdFieldIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productPropertyRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    public function setup(): void
    {
        $this->productPropertyRepository = $this->getContainer()->get('product_property.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
    }

    public function testPropertyIndexing(): void
    {
        $productId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $yellowId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $this->productRepository->create([
            [
                'id' => $productId,
                'name' => __FUNCTION__,
                'productNumber' => $productId,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'properties' => [
                    ['id' => $redId, 'name' => 'red', 'group' => ['id' => $productId, 'name' => 'color']],
                    ['id' => $greenId, 'name' => 'green', 'groupId' => $productId],
                ],
            ],
        ], $context);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        /** @var ProductEntity $product */
        static::assertContains($redId, $product->getPropertyIds());
        static::assertNotContains($yellowId, $product->getPropertyIds());
        static::assertContains($greenId, $product->getPropertyIds());

        $this->productPropertyRepository->delete(
            [['productId' => $productId, 'optionId' => $redId]],
            $context
        );

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        /** @var ProductEntity $product */
        static::assertNotContains($redId, $product->getPropertyIds());
        static::assertNotContains($yellowId, $product->getPropertyIds());
        static::assertContains($greenId, $product->getPropertyIds());

        $this->productPropertyRepository->create(
            [['productId' => $productId, 'optionId' => $redId]],
            $context
        );

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        /** @var ProductEntity $product */
        static::assertContains($redId, $product->getPropertyIds());
        static::assertNotContains($yellowId, $product->getPropertyIds());
        static::assertContains($greenId, $product->getPropertyIds());

        $this->productRepository->update(
            [
                [
                    'id' => $productId,
                    'properties' => [
                        ['id' => $yellowId, 'name' => 'yellow', 'groupId' => $productId],
                    ],
                ],
            ],
            $context
        );

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        /** @var ProductEntity $product */
        static::assertContains($redId, $product->getPropertyIds());
        static::assertContains($yellowId, $product->getPropertyIds());
        static::assertContains($greenId, $product->getPropertyIds());
    }
}
