<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AssociationExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendableDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\FkFieldExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\InvalidReferenceExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ReferenceVersionExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarRuntimeExtension;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class EntityExtensionTest extends TestCase
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
    private $priceRepository;

    /**
     * @var EntityRepository
     */
    private $categoryRepository;

    /**
     * @var EntityWriter
     */
    private $writer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->priceRepository = $this->getContainer()->get('product_price.repository');
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->writer = $this->getContainer()->get(EntityWriter::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->getContainer()->get(ProductDefinition::class)->getFields()->remove('myPrices');
        $this->getContainer()->get(ProductDefinition::class)->getFields()->remove('myCategories');

        $this->removeExtension(ScalarExtension::class, ScalarRuntimeExtension::class, AssociationExtension::class, ReferenceVersionExtension::class, InvalidReferenceExtension::class);
    }

    public function testICanWriteAndReadManyToOneAssociationExtension(): void
    {
        $this->connection->rollBack();

        try {
            $this->connection->executeStatement('ALTER TABLE `product` ADD COLUMN my_tax_id binary(16) NULL');
        } catch (Exception) {
        }

        $this->connection->beginTransaction();

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField(
            (new ManyToOneAssociationField('myTax', 'my_tax_id', TaxDefinition::class, 'id'))->addFlags(new ApiAware(), new Extension())
        );
        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField(
            (new FkField('my_tax_id', 'myTaxId', TaxDefinition::class))->addFlags(new ApiAware(), new Extension())
        );

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => $id,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'myTax' => ['id' => $id, 'name' => 'my-tax', 'taxRate' => 50],
        ];

        $this->productRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myTax');

        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(ProductEntity::class, $product);

        static::assertTrue($product->hasExtension('myTax'));

        /** @var TaxEntity $tax */
        $tax = $product->getExtension('myTax');
        static::assertInstanceOf(TaxEntity::class, $tax);

        static::assertSame('my-tax', $tax->getName());

        $this->connection->rollBack();

        $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN my_tax_id');

        $this->connection->beginTransaction();

        $this->getContainer()->get(ProductDefinition::class)->getFields()->remove('myTax');
        $this->getContainer()->get(ProductDefinition::class)->getFields()->remove('myTaxId');
    }

    public function testICanWriteOneToManyAssociationsExtensions(): void
    {
        $field = (new OneToManyAssociationField('myPrices', ProductPriceDefinition::class, 'product_id'))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_price WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $data['extensions']['myPrices'] = $data['myPrices'];
        unset($data['myPrices']);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_price WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);
    }

    public function testICanReadOneToManyAssociationsExtensionsInBasic(): void
    {
        $field = (new OneToManyAssociationField('myPrices', ProductPriceDefinition::class, 'product_id'))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myPrices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myPrices'));
        static::assertInstanceOf(ProductPriceCollection::class, $product->getExtension('myPrices'));
        static::assertCount(2, $product->getExtension('myPrices'));
    }

    public function testICanReadOneToManyAssociationsExtensionsNotInBasic(): void
    {
        $field = (new OneToManyAssociationField('myPrices', ProductPriceDefinition::class, 'product_id'))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myPrices'));

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myPrices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myPrices'));
        static::assertInstanceOf(ProductPriceCollection::class, $product->getExtension('myPrices'));
        static::assertCount(2, $product->getExtension('myPrices'));

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('extensions.myPrices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myPrices'));
        static::assertInstanceOf(ProductPriceCollection::class, $product->getExtension('myPrices'));
        static::assertCount(2, $product->getExtension('myPrices'));
    }

    public function testICanSearchOneToManyAssociationsExtensions(): void
    {
        $field = (new OneToManyAssociationField('myPrices', ProductPriceDefinition::class, 'product_id'))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.myPrices.price.gross', 15));
        $criteria->addFilter(new EqualsFilter('product.ean', 'test'));

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myPrices'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.extensions.myPrices.price.gross', 15));
        $criteria->addFilter(new EqualsFilter('product.ean', 'test'));

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myPrices'));
    }

    public function testICanReadPaginatedOneToManyAssociationsExtensions(): void
    {
        $field = (new OneToManyAssociationField('myPrices', ProductPriceDefinition::class, 'product_id'))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getPricesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myPrices'));

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('myPrices')->setLimit(1);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myPrices'));
        static::assertInstanceOf(ProductPriceCollection::class, $product->getExtension('myPrices'));
        static::assertCount(1, $product->getExtension('myPrices'));
    }

    public function testICanWriteManyToManyAssociationsExtensions(): void
    {
        $field = (new ManyToManyAssociationField(
            'myCategories',
            CategoryDefinition::class,
            ProductCategoryDefinition::class,
            'product_id',
            'category_id'
        ))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_category WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $data['extensions']['myCategories'] = $data['myCategories'];
        unset($data['myCategories']);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_category WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);
    }

    public function testICanReadManyToManyAssociationsExtensionsInBasic(): void
    {
        $field = (new ManyToManyAssociationField(
            'myCategories',
            CategoryDefinition::class,
            ProductCategoryDefinition::class,
            'product_id',
            'category_id'
        ))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myCategories');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $product->getExtension('myCategories'));
        static::assertCount(2, $product->getExtension('myCategories'));
    }

    public function testICanReadManyToManyAssociationsExtensionsNotInBasic(): void
    {
        $field = (new ManyToManyAssociationField(
            'myCategories',
            CategoryDefinition::class,
            ProductCategoryDefinition::class,
            'product_id',
            'category_id'
        ))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myCategories'));

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myCategories');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $product->getExtension('myCategories'));
        static::assertCount(2, $product->getExtension('myCategories'));

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myCategories');

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $product->getExtension('myCategories'));
        static::assertCount(2, $product->getExtension('myCategories'));
    }

    public function testICanSearchManyToManyAssociationsExtensions(): void
    {
        $field = (new ManyToManyAssociationField(
            'myCategories',
            CategoryDefinition::class,
            ProductCategoryDefinition::class,
            'product_id',
            'category_id'
        ))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.myCategories.level', 1));
        $criteria->addFilter(new EqualsFilter('product.myCategories.name', 'test'));

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myCategories'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.extensions.myCategories.level', 1));
        $criteria->addFilter(new EqualsFilter('product.myCategories.name', 'test'));

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myCategories'));
    }

    public function testICanReadPaginatedManyToManyAssociationsExtensions(): void
    {
        $field = (new ManyToManyAssociationField(
            'myCategories',
            CategoryDefinition::class,
            ProductCategoryDefinition::class,
            'product_id',
            'category_id'
        ))->addFlags(new ApiAware(), new Extension());

        $this->getContainer()->get(ProductDefinition::class)->getFields()->addNewField($field);

        $id = Uuid::randomHex();

        $data = $this->getCategoriesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->productRepository->search(new Criteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myCategories'));

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('extensions.myCategories')->setLimit(2);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $product->getExtension('myCategories'));
        static::assertCount(2, $product->getExtension('myCategories'));

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('myCategories')->setLimit(2);

        /** @var ProductEntity $product */
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $product->getExtension('myCategories'));
        static::assertCount(2, $product->getExtension('myCategories'));
    }

    public function testICantAddScalarExtensions(): void
    {
        static::expectException(\Exception::class);
        static::expectExceptionMessage('Only AssociationFields, FkFields/ReferenceVersionFields for a ManyToOneAssociationField or fields flagged as Runtime can be added as Extension.');

        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, ScalarExtension::class);

        $this->getContainer()->get(ExtendableDefinition::class)->getFields()->has('test');
    }

    public function testICanAddRuntimeExtensions(): void
    {
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, ScalarRuntimeExtension::class);

        static::assertTrue($this->getContainer()->get(ExtendableDefinition::class)->getFields()->has('test'));
    }

    public function testICanAddFkFieldsAsExtensions(): void
    {
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, FkFieldExtension::class);

        static::assertTrue($this->getContainer()->get(ExtendableDefinition::class)->getFields()->has('test'));
    }

    public function testICanAddAssociationExtensions(): void
    {
        $this->registerDefinition(ExtendedDefinition::class);
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, AssociationExtension::class);

        static::assertTrue($this->getContainer()->get(ExtendableDefinition::class)->getFields()->has('toOne'));
        static::assertTrue($this->getContainer()->get(ExtendableDefinition::class)->getFields()->has('toMany'));
    }

    public function testICanAddReferenceVersionAsExtensionWithValidManyToOneAssociation(): void
    {
        $this->registerDefinition(ExtendedDefinition::class);
        $this->registerDefinitionWithExtensions(ExtendableDefinition::class, ReferenceVersionExtension::class);

        static::assertTrue($this->getContainer()->get(ExtendableDefinition::class)->getFields()->has('toOne'));
        static::assertTrue($this->getContainer()->get(ExtendableDefinition::class)->getFields()->has('extendedVersionId'));
    }

    private function getPricesData(string $id): array
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create(
            [
                ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
                ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
            ],
            Context::createDefaultContext()
        );

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'ean' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'myPrices' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        return $data;
    }

    private function getCategoriesData(string $id): array
    {
        $categoryA = Uuid::randomHex();
        $categoryB = Uuid::randomHex();

        $this->getContainer()->get('category.repository')->create(
            [
                ['id' => $categoryA, 'name' => 'test', 'position' => 0, 'level' => 1],
                ['id' => $categoryB, 'name' => 'test', 'position' => 1, 'level' => 2],
            ],
            Context::createDefaultContext()
        );

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'category test',
            'ean' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'myCategories' => [
                [
                    'id' => $categoryA,
                ],
                [
                    'id' => $categoryB,
                ],
            ],
        ];

        return $data;
    }
}
