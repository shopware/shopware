<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ManyToOneProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\OneToOneInheritedProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\OneToOneInheritedProductExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtensionSelfReferenced;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ToOneProductExtension;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;

/**
 * @internal
 */
class EntityExtensionReadTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $productRepository;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinition(ManyToOneProductDefinition::class);
        $this->registerDefinition(OneToOneInheritedProductDefinition::class);
        $this->registerDefinitionWithExtensions(
            ProductDefinition::class,
            ProductExtension::class,
            ProductExtensionSelfReferenced::class,
            ToOneProductExtension::class,
            OneToOneInheritedProductExtension::class
        );

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $this->connection->rollBack();

        $this->connection->executeStatement('
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

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `many_to_one_product`;
            CREATE TABLE `many_to_one_product` (
                `id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            )
        ');

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `product_one_to_one_inherited`;
            CREATE TABLE `product_one_to_one_inherited` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `my_date` DATETIME(3) DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`, `version_id`),
                CONSTRAINT `fk.one_to_one_product_inherited.product_id__product_version_id` FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');

        $this->connection->executeStatement('
            ALTER TABLE `product`
                ADD COLUMN `linked_product_id` binary(16) NULL,
                ADD COLUMN `linked_product_version_id` binary(16) NULL,
                ADD COLUMN `many_to_one_id` binary(16) NULL,
                ADD COLUMN `oneToOneInherited` binary(16) NULL,
                ADD CONSTRAINT `fk.product.many_to_one_id` FOREIGN KEY (`many_to_one_id`) REFERENCES `many_to_one_product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('
            ALTER TABLE `product`
            DROP FOREIGN KEY `fk.product.many_to_one_id`;
        ');
        $this->connection->executeStatement('DROP TABLE `extended_product`');
        $this->connection->executeStatement('DROP TABLE `many_to_one_product`');
        $this->connection->executeStatement('DROP TABLE `product_one_to_one_inherited`');
        $this->connection->executeStatement('
            ALTER TABLE `product`
            DROP COLUMN `linked_product_id`,
            DROP COLUMN `linked_product_version_id`,
            DROP COLUMN `many_to_one_id`,
            DROP COLUMN `oneToOneInherited`;
        ');
        $this->connection->beginTransaction();

        $this->removeExtension(ProductExtension::class);
        $this->removeExtension(ProductExtensionSelfReferenced::class);
        $this->removeExtension(ToOneProductExtension::class);
        $this->removeExtension(OneToOneInheritedProductExtension::class);

        parent::tearDown();
    }

    public function testICanAddAManyToOneAsExtension(): void
    {
        $productId = Uuid::randomHex();
        $extendableId = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manyToOne' => [
                    'id' => $extendableId,
                ],
            ],
        ], Context::createDefaultContext());

        $created = $this->connection->fetchAllAssociative('SELECT * FROM many_to_one_product');

        static::assertCount(1, $created);
        $reference = array_shift($created);
        static::assertIsArray($reference);
        static::assertSame($extendableId, Uuid::fromBytesToHex($reference['id']));

        $criteria = new Criteria();
        $criteria->addAssociation('manyToOne');

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame($productId, $product->getId());

        static::assertTrue($product->hasExtension('manyToOne'));
        /** @var ArrayEntity|null $extension */
        $extension = $product->getExtension('manyToOne');

        static::assertInstanceOf(ArrayEntity::class, $extension);
        static::assertEquals($extendableId, $extension->get('id'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('manyToOne.id', $extendableId));

        $products = $this->productRepository->searchIds($criteria, Context::createDefaultContext());
        static::assertTrue($products->has($productId));
    }

    public function testICanReadManyToOneOverAssociation(): void
    {
        $productId = Uuid::randomHex();
        $extendableId = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manyToOne' => [
                    'id' => $extendableId,
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$extendableId]);
        $criteria->addAssociation('products');

        /** @var EntityRepository $manyToOneRepo */
        $manyToOneRepo = $this->getContainer()->get('many_to_one_product.repository');
        $manyToOne = $manyToOneRepo->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(ArrayEntity::class, $manyToOne);
        static::assertCount(1, $manyToOne->get('products'));
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

        /** @var EntityCollection<ArrayEntity> $productExtensions */
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

    public function testVariantDoesNotInheritOneToOneAssociationFromParent(): void
    {
        $myDate = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-11-03 13:37:00')->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $context = Context::createDefaultContext();

        $this->productRepository->create([
            [
                'id' => $productId = Uuid::randomHex(),
                'productNumber' => 'SW10001',
                'stock' => 1,
                'name' => 'Test product',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'oneToOneInherited' => [
                    'myDate' => $myDate,
                ],
            ],
        ], $context);

        $this->productRepository->create([
            [
                'id' => $variantId = Uuid::randomHex(),
                'productNumber' => 'SW10001.1',
                'parentId' => $productId,
                'stock' => 1,
            ],
        ], $context);

        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();

        $variant = $context->enableInheritance(fn (Context $context) => $this->productRepository->search(new Criteria([$variantId]), $context)->first());

        static::assertTrue($product->hasExtension('oneToOneInherited'));
        /** @var ArrayEntity $extension */
        $extension = $product->getExtension('oneToOneInherited');
        static::assertEquals($myDate, $extension->get('myDate')->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        // This should be true
        static::assertTrue($variant->hasExtension('oneToOneInherited'));
        $extension = $variant->getExtension('oneToOneInherited');
        // This should equal the parent product's one-to-one-associated myDate
        static::assertEquals($myDate, $extension->get('myDate')->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }
}
