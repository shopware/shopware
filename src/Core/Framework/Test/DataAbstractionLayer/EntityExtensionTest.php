<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Extension;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class EntityExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;

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

    protected function setUp()
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->priceRepository = $this->getContainer()->get('product_price_rule.repository');
        $this->categoryRepository = $this->getContainer()->get('category.repository');

        $this->writer = $this->getContainer()->get(EntityWriter::class);
    }

    protected function tearDown()
    {
        parent::tearDown();
        ProductDefinition::getFields()->remove('myPrices');
        ProductDefinition::getFields()->remove('myCategories');
    }

    public function testICanWriteOneToManyAssociationsExtensions()
    {
        $field = (new OneToManyAssociationField('myPrices', ProductPriceRuleDefinition::class, 'product_id', true))
            ->addFlags(new Extension());

        ProductDefinition::getFields()->add($field);

        $id = Uuid::uuid4()->getHex();

        $data = $this->getPricesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAll(
            'SELECT * FROM product_price_rule WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);

        $id = Uuid::uuid4()->getHex();

        $data = $this->getPricesData($id);

        $data['extensions']['myPrices'] = $data['myPrices'];
        unset($data['myPrices']);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAll(
            'SELECT * FROM product_price_rule WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);
    }

    public function testICanReadOneToManyAssociationsExtensionsInBasic()
    {
        $field = (new OneToManyAssociationField('myPrices', ProductPriceRuleDefinition::class, 'product_id', true))
            ->addFlags(new Extension());

        ProductDefinition::getFields()->add($field);

        $id = Uuid::uuid4()->getHex();

        $data = $this->getPricesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myPrices'));
        static::assertInstanceOf(ProductPriceRuleCollection::class, $product->getExtension('myPrices'));
        static::assertCount(2, $product->getExtension('myPrices'));
    }

    public function testICanReadOneToManyAssociationsExtensionsNotInBasic()
    {
        $field = (new OneToManyAssociationField('myPrices', ProductPriceRuleDefinition::class, 'product_id', false))
            ->addFlags(new Extension());

        ProductDefinition::getFields()->add($field);

        $id = Uuid::uuid4()->getHex();

        $data = $this->getPricesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myPrices'));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('product.myPrices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->read($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myPrices'));
        static::assertInstanceOf(ProductPriceRuleCollection::class, $product->getExtension('myPrices'));
        static::assertCount(2, $product->getExtension('myPrices'));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('product.extensions.myPrices');

        /** @var ProductEntity $product */
        $product = $this->productRepository->read($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myPrices'));
        static::assertInstanceOf(ProductPriceRuleCollection::class, $product->getExtension('myPrices'));
        static::assertCount(2, $product->getExtension('myPrices'));
    }

    public function testICanSearchOneToManyAssociationsExtensions()
    {
        $field = (new OneToManyAssociationField('myPrices', ProductPriceRuleDefinition::class, 'product_id', false))
            ->addFlags(new Extension());

        ProductDefinition::getFields()->add($field);

        $id = Uuid::uuid4()->getHex();

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

    public function testICanReadPaginatedOneToManyAssociationsExtensions()
    {
        $field = (new OneToManyAssociationField('myPrices', ProductPriceRuleDefinition::class, 'product_id', false))
            ->addFlags(new Extension());

        ProductDefinition::getFields()->add($field);

        $id = Uuid::uuid4()->getHex();

        $data = $this->getPricesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myPrices'));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('product.myPrices', new PaginationCriteria(1));

        /** @var ProductEntity $product */
        $product = $this->productRepository->read($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myPrices'));
        static::assertInstanceOf(ProductPriceRuleCollection::class, $product->getExtension('myPrices'));
        static::assertCount(1, $product->getExtension('myPrices'));
    }

    public function testICanWriteManyToManyAssociationsExtensions()
    {
        $field = (new ManyToManyAssociationField(
            'myCategories', CategoryDefinition::class,
            ProductCategoryDefinition::class, false, 'product_id', 'category_id'
        ))->addFlags(new Extension());

        ProductDefinition::getFields()->add($field);

        $id = Uuid::uuid4()->getHex();

        $data = $this->getCategoriesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAll(
            'SELECT * FROM product_category WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);

        $id = Uuid::uuid4()->getHex();

        $data = $this->getCategoriesData($id);

        $data['extensions']['myCategories'] = $data['myCategories'];
        unset($data['myCategories']);

        $this->productRepository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAll(
            'SELECT * FROM product_category WHERE product_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertCount(2, $count);
    }

    public function testICanReadManyToManyAssociationsExtensionsInBasic()
    {
        $field = (new ManyToManyAssociationField(
            'myCategories', CategoryDefinition::class,
            ProductCategoryDefinition::class, true, 'product_id', 'category_id'
        ))->addFlags(new Extension());

        ProductDefinition::getFields()->add($field);

        $id = Uuid::uuid4()->getHex();

        $data = $this->getCategoriesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $product->getExtension('myCategories'));
        static::assertCount(2, $product->getExtension('myCategories'));
    }

    public function testICanReadManyToManyAssociationsExtensionsNotInBasic()
    {
        $field = (new ManyToManyAssociationField(
            'myCategories', CategoryDefinition::class,
            ProductCategoryDefinition::class, false, 'product_id', 'category_id'
        ))->addFlags(new Extension());

        ProductDefinition::getFields()->add($field);

        $id = Uuid::uuid4()->getHex();

        $data = $this->getCategoriesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myCategories'));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('product.myCategories');

        /** @var ProductEntity $product */
        $product = $this->productRepository->read($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $product->getExtension('myCategories'));
        static::assertCount(2, $product->getExtension('myCategories'));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('product.myCategories');

        /** @var ProductEntity $product */
        $product = $this->productRepository->read($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $product->getExtension('myCategories'));
        static::assertCount(2, $product->getExtension('myCategories'));
    }

    public function testICanSearchManyToManyAssociationsExtensions()
    {
        $field = (new ManyToManyAssociationField(
            'myCategories', CategoryDefinition::class,
            ProductCategoryDefinition::class, false, 'product_id', 'category_id'
        ))->addFlags(new Extension());

        ProductDefinition::getFields()->add($field);

        $id = Uuid::uuid4()->getHex();

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

    public function testICanReadPaginatedManyToManyAssociationsExtensions()
    {
        $field = (new ManyToManyAssociationField(
            'myCategories', CategoryDefinition::class,
            ProductCategoryDefinition::class, false, 'product_id', 'category_id'
        ))->addFlags(new Extension());

        ProductDefinition::getFields()->add($field);

        $id = Uuid::uuid4()->getHex();

        $data = $this->getCategoriesData($id);

        $this->productRepository->create([$data], Context::createDefaultContext());

        /** @var ProductEntity $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertFalse($product->hasExtension('myCategories'));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('product.extensions.myCategories', new PaginationCriteria(2));

        /** @var ProductEntity $product */
        $product = $this->productRepository->read($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $product->getExtension('myCategories'));
        static::assertCount(2, $product->getExtension('myCategories'));

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('product.myCategories', new PaginationCriteria(2));

        /** @var ProductEntity $product */
        $product = $this->productRepository->read($criteria, Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertTrue($product->hasExtension('myCategories'));
        static::assertInstanceOf(CategoryCollection::class, $product->getExtension('myCategories'));
        static::assertCount(2, $product->getExtension('myCategories'));
    }

    private function getPricesData($id): array
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create(
            [
                ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
                ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
            ],
            Context::createDefaultContext()
        );

        $data = [
            'id' => $id,
            'name' => 'price test',
            'ean' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'myPrices' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 15, 'net' => 10],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 10, 'net' => 8],
                ],
            ],
        ];

        return $data;
    }

    private function getCategoriesData($id): array
    {
        $categoryA = Uuid::uuid4()->getHex();
        $categoryB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('category.repository')->create(
            [
                ['id' => $categoryA, 'name' => 'test', 'position' => 0, 'level' => 1],
                ['id' => $categoryB, 'name' => 'test', 'position' => 1, 'level' => 2],
            ],
            Context::createDefaultContext()
        );

        $data = [
            'id' => $id,
            'name' => 'category test',
            'ean' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
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
