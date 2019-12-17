<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtensionSelfReferenced;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;

class EntityExtensionReadTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinitionWithExtensions(ProductDefinition::class, ProductExtension::class, ProductExtensionSelfReferenced::class);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $this->connection->rollBack();

        $this->connection->executeQuery('
            DROP TABLE IF EXISTS `extended_product`; 
            CREATE TABLE `extended_product` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `product_id` BINARY(16) NULL,
                `language_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.extended_product.id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`),
                CONSTRAINT `fk.extended_product.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
            )
        ');

        $this->connection->executeQuery('
            ALTER TABLE `product` ADD COLUMN `linked_product_id` binary(16) NULL, ADD COLUMN `linked_product_version_id` binary(16) NULL
        ');

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeQuery('DROP TABLE `extended_product`');
        $this->connection->executeQuery('
            ALTER TABLE `product` DROP COLUMN `linked_product_id`, DROP COLUMN `linked_product_version_id`;
        ');
        $this->connection->beginTransaction();

        $this->removeExtension(ProductExtension::class);
        $this->removeExtension(ProductExtensionSelfReferenced::class);

        parent::tearDown();
    }

    public function testICanAddAManyToOneAsExtension(): void
    {
        $productId = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manyToOne' => [
                    'productId' => $productId,
                    'name' => 'test',
                ],
            ],
        ], Context::createDefaultContext());

        $created = $this->connection->fetchAll('SELECT * FROM extended_product');

        static::assertCount(1, $created);
        $reference = array_shift($created);
        static::assertSame($productId, Uuid::fromBytesToHex($reference['product_id']));

        $criteria = new Criteria();
        $criteria->addAssociation('manyToOne');

        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        /** @var ProductEntity $product */
        static::assertSame($productId, $product->getId());

        static::assertTrue($product->hasExtension('manyToOne'));
        $extension = $product->getExtension('manyToOne');

        /** @var ArrayEntity $extension */
        static::assertInstanceOf(ArrayEntity::class, $extension);
        static::assertEquals('test', $extension->get('name'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('manyToOne.name', 'test'));
        $criteria->addExtension('test', new ArrayEntity());

        $products = $this->productRepository->searchIds($criteria, Context::createDefaultContext());
        static::assertTrue($products->has($productId));
    }

    public function testICanReadNestedAssociationsFromToOneExtensions(): void
    {
        $productId = Uuid::randomHex();

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
                'toOne' => [
                    'name' => 'test',
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('toOne.toOne');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->get($productId);
        static::assertTrue($product->hasExtension('toOne'));

        /** @var ArrayEntity $extension */
        $extension = $product->getExtension('toOne');
        static::assertInstanceOf(ProductEntity::class, $extension->get('toOne'));
    }

    public function testICanReadNestedAssociationsFromToManyExtensions(): void
    {
        $productId = Uuid::randomHex();

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
                'oneToMany' => [
                    ['name' => 'test 1', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'test 2', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('oneToMany.language');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->get($productId);

        static::assertTrue($product->hasExtension('oneToMany'));

        /** @var EntityCollection $productExtensions */
        $productExtensions = $product->getExtension('oneToMany');
        static::assertInstanceOf(EntityCollection::class, $productExtensions);
        static::assertCount(2, $productExtensions);

        $productExtension = $productExtensions->first();
        static::assertInstanceOf(LanguageEntity::class, $productExtension->get('language'));
    }

    public function testReadSelfReferencedAssociationsFromToManyExtensions(): void
    {
        $linkedProductId = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $linkedProductId,
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

        $productId = Uuid::randomHex();

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
                'linkedProductId' => $linkedProductId,
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('ManyToOneSelfReference');
        $criteria->addAssociation('ManyToOneSelfReferenceAutoload');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->get($productId);

        // Self Reference without autoload should be loaded
        static::assertTrue($product->hasExtension('ManyToOneSelfReference'));
        // Self Reference with autoload should NOT be loaded
        static::assertFalse($product->hasExtension('ManyToOneSelfReferenceAutoload'));

        static::assertEquals($linkedProductId, $product->getExtension('ManyToOneSelfReference')->getVars()['id']);
    }
}
