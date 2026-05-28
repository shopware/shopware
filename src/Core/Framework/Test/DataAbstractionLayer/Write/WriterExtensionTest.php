<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class WriterExtensionTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour;
    use IntegrationTestBehaviour;

    private Connection $connection;

    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinitionWithExtensions(ProductDefinition::class, ProductExtension::class);

        $this->productRepository = $this->getContainer()->get('product.repository');

        $this->connection->rollBack();

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `extended_product`;
            CREATE TABLE `extended_product` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `product_id` BINARY(16) NULL,
                `product_version_id` BINARY(16) NOT NULL DEFAULT 0x0fa91ce3e96a4bc2be4bd9ce752c3425,
                `language_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.extended_product.id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`),
                CONSTRAINT `fk.extended_product.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
            )
        ');

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE `extended_product`');
        $this->connection->beginTransaction();

        $this->removeExtension(ProductExtension::class);

        parent::tearDown();
    }

    public function testWriteExtensionWithExtensionKey(): void
    {
        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        $this->productRepository->update([
            [
                'id' => $productId,
                'extensions' => [
                    'oneToMany' => [
                        ['name' => 'test 1', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                        ['name' => 'test 2', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('oneToMany');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->get($productId);
        static::assertTrue($product->hasExtension('oneToMany'));

        /** @var EntityCollection<ArrayEntity> $productExtensions */
        $productExtensions = $product->getExtension('oneToMany');
        $productExtensions->sort(static fn (ArrayEntity $a, ArrayEntity $b) => $a->get('name') <=> $b->get('name'));

        static::assertInstanceOf(EntityCollection::class, $productExtensions);
        static::assertCount(2, $productExtensions);
        $first = $productExtensions->first();
        static::assertInstanceOf(ArrayEntity::class, $first);
        static::assertEquals('test 1', $first->get('name'));
        $last = $productExtensions->last();
        static::assertInstanceOf(ArrayEntity::class, $last);
        static::assertEquals('test 2', $last->get('name'));
    }

    public function testCanWriteExtensionWithoutExtensionKey(): void
    {
        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        $this->productRepository->update([
            [
                'id' => $productId,
                'oneToMany' => [
                    ['name' => 'test 1', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'test 2', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('oneToMany');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->get($productId);
        static::assertTrue($product->hasExtension('oneToMany'));

        /** @var EntityCollection<ArrayEntity> $productExtensions */
        $productExtensions = $product->getExtension('oneToMany');
        $productExtensions->sort(static fn (ArrayEntity $a, ArrayEntity $b) => $a->get('name') <=> $b->get('name'));

        static::assertInstanceOf(EntityCollection::class, $productExtensions);
        static::assertCount(2, $productExtensions);
        $first = $productExtensions->first();
        static::assertInstanceOf(ArrayEntity::class, $first);
        static::assertEquals('test 1', $first->get('name'));
        $last = $productExtensions->last();
        static::assertInstanceOf(ArrayEntity::class, $last);
        static::assertEquals('test 2', $last->get('name'));
    }

    private function createProduct(string $productId): void
    {
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'shopware AG',
                    'link' => 'https://shopware.com',
                ],
            ],
        ], Context::createDefaultContext());
    }
}
