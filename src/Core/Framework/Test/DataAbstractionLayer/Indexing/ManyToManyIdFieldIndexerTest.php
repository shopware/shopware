<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

/**
 * @internal
 */
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

    /**
     * @var TestDataCollection
     */
    private $testData;

    public function setup(): void
    {
        $this->productPropertyRepository = $this->getContainer()->get('product_property.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
    }

    public function testPropertyIndexing(): void
    {
        $data = new TestDataCollection();

        $this->createProduct($data);

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search(new Criteria([$data->get('product')]), Context::createDefaultContext())->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertContains($data->get('red'), $product->getPropertyIds());
        static::assertNotContains($data->create('yellow'), $product->getPropertyIds());
        static::assertContains($data->get('green'), $product->getPropertyIds());

        $this->productPropertyRepository->delete(
            [['productId' => $data->get('product'), 'optionId' => $data->get('red')]],
            Context::createDefaultContext()
        );

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search(new Criteria([$data->get('product')]), Context::createDefaultContext())->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertNotContains($data->get('red'), $product->getPropertyIds());
        static::assertNotContains($data->get('yellow'), $product->getPropertyIds());
        static::assertContains($data->get('green'), $product->getPropertyIds());

        $this->productPropertyRepository->create(
            [['productId' => $data->get('product'), 'optionId' => $data->get('red')]],
            Context::createDefaultContext()
        );

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search(new Criteria([$data->get('product')]), Context::createDefaultContext())->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertContains($data->get('red'), $product->getPropertyIds());
        static::assertNotContains($data->get('yellow'), $product->getPropertyIds());
        static::assertContains($data->get('green'), $product->getPropertyIds());

        $this->productRepository->update(
            [
                [
                    'id' => $data->get('product'),
                    'properties' => [
                        ['id' => $data->get('yellow'), 'name' => 'yellow', 'groupId' => $data->get('product')],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search(new Criteria([$data->get('product')]), Context::createDefaultContext())->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertContains($data->get('red'), $product->getPropertyIds());
        static::assertContains($data->get('yellow'), $product->getPropertyIds());
        static::assertContains($data->get('green'), $product->getPropertyIds());
    }

    public function testResetRelation(): void
    {
        $data = new TestDataCollection();

        $this->createProduct($data);

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search(new Criteria([$data->get('product')]), Context::createDefaultContext())->first();

        // product is created with red and green, assert both ids are inside the many to many id field
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertCount(2, $product->getPropertyIds());
        static::assertContains($data->get('red'), $product->getPropertyIds());
        static::assertContains($data->get('green'), $product->getPropertyIds());

        // reset relation, the product has now no more properties
        $this->productPropertyRepository->delete([
            ['productId' => $data->get('product'), 'optionId' => $data->get('red')],
            ['productId' => $data->get('product'), 'optionId' => $data->get('green')],
        ], Context::createDefaultContext());

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search(new Criteria([$data->get('product')]), Context::createDefaultContext())->first();

        static::assertInstanceOf(ProductEntity::class, $product);

        static::assertNull($product->getPropertyIds());

        // test re-assignment
        $this->productPropertyRepository->create([
            ['productId' => $data->get('product'), 'optionId' => $data->get('red')],
            ['productId' => $data->get('product'), 'optionId' => $data->get('green')],
        ], Context::createDefaultContext());

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search(new Criteria([$data->get('product')]), Context::createDefaultContext())->first();

        static::assertInstanceOf(ProductEntity::class, $product);

        static::assertCount(2, $product->getPropertyIds());
        static::assertContains($data->get('red'), $product->getPropertyIds());
        static::assertContains($data->get('green'), $product->getPropertyIds());
    }

    private function createProduct(TestDataCollection $data): void
    {
        $this->productRepository->create(
            [
                [
                    'id' => $data->create('product'),
                    'name' => __FUNCTION__,
                    'productNumber' => $data->get('product'),
                    'tax' => ['name' => 'test', 'taxRate' => 15],
                    'stock' => 10,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                    ],
                    'properties' => [
                        ['id' => $data->create('red'), 'name' => 'red', 'group' => ['id' => $data->get('product'), 'name' => 'color']],
                        ['id' => $data->create('green'), 'name' => 'green', 'groupId' => $data->get('product')],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }
}
