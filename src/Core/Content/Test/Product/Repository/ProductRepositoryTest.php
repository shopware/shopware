<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordCollection;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordEntity;
use Shopware\Core\Content\Product\Exception\DuplicateProductNumberException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @group slow
 */
class ProductRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    final public const TEST_LANGUAGE_ID = 'cc72c24b82684d72a4ce91054da264bf';
    final public const TEST_LOCALE_ID = 'cf735c44dc7b4428bb3870fe4ffea2df';
    final public const TEST_LANGUAGE_LOCALE_CODE = 'sw-AG';

    private EntityRepository $repository;

    private EventDispatcherInterface $eventDispatcher;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('product.repository');
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testWritePrice(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->repository->create([$data], $this->context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        static::assertInstanceOf(PriceCollection::class, $product->getPrice());

        static::assertEquals(
            new Price(Defaults::CURRENCY, 10, 15, false),
            $product->getPrice()->getCurrencyPrice(Defaults::CURRENCY)
        );
    }

    public function testWriteMultipleCurrencyPrices(): void
    {
        $id = Uuid::randomHex();

        $this->getContainer()->get('currency.repository')->create(
            [
                [
                    'id' => $id,
                    'factor' => 2,
                    'shortName' => 'test',
                    'name' => 'name',
                    'symbol' => 'A',
                    'isoCode' => 'XX',
                    'decimalPrecision' => 2,
                    'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                    'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                ],
            ],
            $this->context
        );

        $data = [
            'id' => $id,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ['currencyId' => $id, 'gross' => 150, 'net' => 100, 'linked' => true],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->repository->create([$data], $this->context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        static::assertInstanceOf(PriceCollection::class, $product->getPrice());

        static::assertCount(2, $product->getPrice());

        static::assertEquals(
            new Price(Defaults::CURRENCY, 10, 15, false),
            $product->getCurrencyPrice(Defaults::CURRENCY)
        );

        static::assertEquals(
            new Price($id, 100, 150, true),
            $product->getCurrencyPrice($id)
        );
    }

    public function testVariantNameIsNullable(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                // name should be required
                'name' => 'parent',
            ],
            [
                'id' => $variantId,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $parentId,
                'stock' => 15,
            ],
        ];

        try {
            $this->repository->create($products, $this->context);

            $update = ['name' => null, 'id' => $variantId];

            $this->repository->update([$update], $this->context);
        } catch (\Exception) {
            static::fail('Can not reset variant name to null');
        }

        /** @var ProductEntity $variant */
        $variant = $this->repository
            ->search(new Criteria([$variantId]), $this->context)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $variant);

        static::assertNull($variant->getName());
    }

    public function testUpdatedProductChildCountOnVariantDeletion(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $secondVariantId = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                // name should be required
                'name' => 'parent',
            ],
            [
                'id' => $variantId,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $parentId,
                'stock' => 15,
            ],
            [
                'id' => $secondVariantId,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $parentId,
                'stock' => 15,
            ],
        ];

        $this->repository->create($products, $this->context);
        $delete = $this->repository->delete([['id' => $secondVariantId]], $this->context);

        $this->runWorker();

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $delete);

        $ids = $delete->getPrimaryKeys('product');

        static::assertContains($secondVariantId, $ids);

        $this->runWorker();

        $product = $this->repository
            ->search(new Criteria([$parentId]), $this->context)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertEquals(1, $product->getChildCount());
    }

    public function testNameIsRequiredForParent(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $e = null;

        try {
            $this->repository->create([$data], $this->context);
        } catch (WriteException $e) {
        }

        static::assertInstanceOf(WriteException::class, $e);

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];
        $this->repository->create([$data], $this->context);

        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);

        $variantId = Uuid::randomHex();

        $data = [
            'id' => $variantId,
            'stock' => 10,
            'productNumber' => 'variant',
            'parentId' => $id,
        ];
        $this->repository->create([$data], $this->context);

        /** @var ProductEntity|null $variant */
        $variant = $this->repository
            ->search(new Criteria([$variantId]), $this->context)
            ->get($variantId);

        static::assertInstanceOf(ProductEntity::class, $variant);

        static::assertNull($variant->getName());
    }

    public function testSearchKeywordIndexerConsidersUpdate(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Default name',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->repository->create([$data], $this->context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('searchKeywords');

        /** @var ProductEntity|null $product */
        $product = $this->repository
            ->search($criteria, $this->context)
            ->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);

        static::assertInstanceOf(ProductSearchKeywordCollection::class, $product->getSearchKeywords());

        $keywords = $product->getSearchKeywords()->map(static fn (ProductSearchKeywordEntity $entity) => $entity->getKeyword());

        static::assertContains('default', $keywords);
        static::assertContains('name', $keywords);

        $update = [
            'id' => $id,
            'name' => 'updated',
        ];

        $this->repository->update([$update], $this->context);

        /** @var ProductEntity|null $product */
        $product = $this->repository
            ->search($criteria, $this->context)
            ->get($id);

        static::assertInstanceOf(ProductEntity::class, $product);

        static::assertInstanceOf(ProductSearchKeywordCollection::class, $product->getSearchKeywords());

        $keywords = $product->getSearchKeywords()->map(static fn (ProductSearchKeywordEntity $entity) => $entity->getKeyword());

        static::assertNotContains('default', $keywords);
        static::assertNotContains('name', $keywords);
        static::assertContains('updated', $keywords);
    }

    public function testWriteCategories(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'asd'],
            ],
        ];

        $this->repository->create([$data], $this->context);

        /** @var array{product_id: string, category_id: string} $record */
        $record = $this->connection->fetchAssociative('SELECT * FROM product_category WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertNotEmpty($record);
        static::assertSame($record['product_id'], Uuid::fromHexToBytes($id));
        static::assertSame($record['category_id'], Uuid::fromHexToBytes($id));

        $record = $this->connection->fetchAssociative('SELECT * FROM category WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertNotEmpty($record);
    }

    public function testWriteProductWithDifferentTaxFormat(): void
    {
        $tax = Uuid::randomHex();

        $data = [
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'without id'],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $tax, 'taxRate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'taxId' => $tax,
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $tax, 'taxRate' => 18],
            ],
        ];

        $this->repository->create($data, $this->context);
        $ids = array_column($data, 'id');
        $criteria = new Criteria($ids);
        $criteria->addAssociation('tax');
        $products = $this->repository->search($criteria, $this->context);

        $product = $products->get($ids[0]);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(TaxEntity::class, $product->getTax());
        static::assertSame('without id', $product->getTax()->getName());
        static::assertSame(19.0, $product->getTax()->getTaxRate());

        $product = $products->get($ids[1]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(TaxEntity::class, $product->getTax());
        static::assertSame($tax, $product->getTaxId());
        static::assertSame($tax, $product->getTax()->getId());
        static::assertSame('with id', $product->getTax()->getName());
        static::assertSame(18.0, $product->getTax()->getTaxRate());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(TaxEntity::class, $product->getTax());
        static::assertSame($tax, $product->getTaxId());
        static::assertSame($tax, $product->getTax()->getId());
        static::assertSame('with id', $product->getTax()->getName());
        static::assertSame(18.0, $product->getTax()->getTaxRate());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(TaxEntity::class, $product->getTax());
        static::assertSame($tax, $product->getTaxId());
        static::assertSame($tax, $product->getTax()->getId());
        static::assertSame('with id', $product->getTax()->getName());
        static::assertSame(18.0, $product->getTax()->getTaxRate());
    }

    public function testWriteProductWithDifferentManufacturerStructures(): void
    {
        $manufacturerId = Uuid::randomHex();

        $data = [
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['name' => 'without id'],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'with id'],
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturerId' => $manufacturerId,
            ],
            [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['taxRate' => 17, 'name' => 'test'],
                'manufacturer' => ['id' => $manufacturerId, 'link' => 'test'],
            ],
        ];

        $this->repository->create($data, $this->context);
        $ids = array_column($data, 'id');
        $criteria = new Criteria($ids);
        $criteria->addAssociation('manufacturer');

        $products = $this->repository->search($criteria, $this->context);

        $product = $products->get($ids[0]);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());
        static::assertSame('without id', $product->getManufacturer()->getName());

        $product = $products->get($ids[1]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());
        static::assertSame($manufacturerId, $product->getManufacturerId());
        static::assertSame($manufacturerId, $product->getManufacturer()->getId());
        static::assertSame('with id', $product->getManufacturer()->getName());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());
        static::assertSame($manufacturerId, $product->getManufacturerId());
        static::assertSame($manufacturerId, $product->getManufacturer()->getId());
        static::assertSame('with id', $product->getManufacturer()->getName());

        $product = $products->get($ids[2]);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());
        static::assertSame($manufacturerId, $product->getManufacturerId());
        static::assertSame($manufacturerId, $product->getManufacturer()->getId());
        static::assertSame('with id', $product->getManufacturer()->getName());
        static::assertSame('test', $product->getManufacturer()->getLink());
    }

    public function testReadAndWriteOfProductManufacturerAssociation(): void
    {
        $id = Uuid::randomHex();

        //check nested events are triggered
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly(2))->method('__invoke');
        $this->eventDispatcher->addListener('product.written', $listener);
        $this->eventDispatcher->addListener('product_manufacturer.written', $listener);

        $this->repository->create([
            [
                'id' => $id,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'manufacturer' => ['name' => 'test'],
            ],
        ], Context::createDefaultContext());

        //validate that nested events are triggered
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly(2))->method('__invoke');
        $this->eventDispatcher->addListener('product.loaded', $listener);
        $this->eventDispatcher->addListener('product_manufacturer.loaded', $listener);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('manufacturer');

        $products = $this->repository->search($criteria, Context::createDefaultContext());

        //check only provided id loaded
        static::assertCount(1, $products);
        static::assertTrue($products->has($id));

        $product = $products->get($id);

        //check data loading is as expected
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame($id, $product->getId());
        static::assertSame('Test', $product->getName());

        static::assertInstanceOf(ProductManufacturerEntity::class, $product->getManufacturer());

        //check nested element loaded
        $manufacturer = $product->getManufacturer();
        static::assertSame('test', $manufacturer->getName());
    }

    public function testReadAndWriteProductPrices(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $ruleA,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $ruleB,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        /** @var ProductCollection $products */
        $products = $this->repository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(1, $products);
        static::assertTrue($products->has($id));

        $product = $products->get($id);

        static::assertSame($id, $product->getId());

        static::assertEquals(new Price(Defaults::CURRENCY, 10, 15, false), $product->getCurrencyPrice(Defaults::CURRENCY));
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(2, $product->getPrices());

        $price = $product->getPrices()->get($ruleA);
        static::assertInstanceOf(ProductPriceEntity::class, $price);
        $currencyPrice = $price->getPrice()->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $currencyPrice);
        static::assertSame(15.0, $currencyPrice->getGross());
        static::assertSame(10.0, $currencyPrice->getNet());

        $price = $product->getPrices()->get($ruleB);
        static::assertInstanceOf(ProductPriceEntity::class, $price);
        $currencyPrice = $price->getPrice()->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $currencyPrice);
        static::assertSame(10.0, $currencyPrice->getGross());
        static::assertSame(8.0, $currencyPrice->getNet());
    }

    public function testProductPricesSortByGrossPrice(): void
    {
        $context = Context::createDefaultContext();
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], $context);

        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'quantityStart' => 1,
                    'quantityEnd' => 10,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 13, 'linked' => false]],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'quantityStart' => 11,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        $this->repository->create([$data], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        /** @var ProductCollection $products */
        $products = $this->repository
            ->search($criteria, $context)
            ->getEntities();

        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(1, $products);
        static::assertTrue($products->has($id));

        $product = $products->get($id);

        static::assertSame($id, $product->getId());

        static::assertEquals(new Price(Defaults::CURRENCY, 10, 15, false), $product->getCurrencyPrice(Defaults::CURRENCY));
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(2, $product->getPrices());

        $product->getPrices()->sortByPrice($context);

        $price = $product->getPrices()->first();
        static::assertSame(10.0, $price->getPrice()->first()->getGross());
        static::assertSame(8.0, $price->getPrice()->first()->getNet());
    }

    public function testProductPricesSortByNetPrice(): void
    {
        $context = Context::createDefaultContext();
        $context->setTaxState(CartPrice::TAX_STATE_NET);

        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], $context);

        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'quantityStart' => 1,
                    'quantityEnd' => 10,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 13, 'linked' => false]],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'quantityStart' => 11,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 19, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        $this->repository->create([$data], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        /** @var ProductCollection $products */
        $products = $this->repository
            ->search($criteria, $context)
            ->getEntities();

        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(1, $products);
        static::assertTrue($products->has($id));

        $product = $products->get($id);

        static::assertSame($id, $product->getId());

        static::assertEquals(new Price(Defaults::CURRENCY, 10, 15, false), $product->getCurrencyPrice(Defaults::CURRENCY));
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(2, $product->getPrices());

        $product->getPrices()->sortByPrice($context);

        $price = $product->getPrices()->first();
        static::assertSame(19.0, $price->getPrice()->first()->getGross());
        static::assertSame(8.0, $price->getPrice()->first()->getNet());
    }

    public function testPriceRulesSorting(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $ruleA = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
        ], Context::createDefaultContext());

        $filterId = Uuid::randomHex();

        $data = [
            [
                'id' => $id,
                'productNumber' => Uuid::randomHex(),
                'name' => 'price test 1',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 500, 'net' => 400, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'ean' => $filterId,
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 14, 'linked' => false]],
                    ],
                ],
            ],
            [
                'id' => $id2,
                'productNumber' => Uuid::randomHex(),
                'name' => 'price test 2',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 500, 'net' => 400, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'ean' => $filterId,
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 5, 'net' => 4, 'linked' => false]],
                    ],
                ],
            ],
            [
                'id' => $id3,
                'productNumber' => Uuid::randomHex(),
                'name' => 'price test 3',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 500, 'net' => 400, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'ean' => $filterId,
                'prices' => [
                    [
                        'quantityStart' => 1,
                        'ruleId' => $ruleA,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->repository->create($data, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.prices.price', FieldSorting::ASCENDING));
        $criteria->addFilter(new EqualsFilter('product.ean', $filterId));

        $context = $this->createContext([$ruleA]);

        $products = $this->repository->searchIds($criteria, $context);

        static::assertSame(
            [$id2, $id3, $id],
            $products->getIds()
        );

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('product.prices.price', FieldSorting::DESCENDING));
        $criteria->addFilter(new EqualsFilter('product.ean', $filterId));

        $products = $this->repository->searchIds($criteria, $context);

        static::assertSame(
            [$id, $id3, $id2],
            $products->getIds()
        );
    }

    public function testVariantInheritancePriceAndName(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 10.0, 'net' => 9, 'linked' => true];
        $parentName = 'T-shirt';
        $greenPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 15.0, 'net' => 14, 'linked' => true];

        $redName = 'Red shirt';

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $parentName,
                'price' => [$parentPrice],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test'],
            ],

            //price should be inherited
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
            ],

            //name should be inherited
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [$greenPrice],
                'parentId' => $parentId,
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);
        $this->repository->create($products, $context);

        $criteria = new Criteria([$redId, $greenId]);
        /** @var ProductCollection $products */
        $products = $this->repository->search($criteria, $context)->getEntities();

        $criteria = new Criteria([$parentId]);
        /** @var ProductCollection $parents */
        $parents = $this->repository->search($criteria, $context);

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        $parent = $parents->get($parentId);
        $red = $products->get($redId);
        $green = $products->get($greenId);

        $parentCurrencyPrice = $parent->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $parentCurrencyPrice);
        static::assertSame($parentPrice['gross'], $parentCurrencyPrice->getGross());
        static::assertSame($parentName, $parent->getName());

        $redCurrencyPrice = $red->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $redCurrencyPrice);
        static::assertSame($parentPrice['gross'], $redCurrencyPrice->getGross());
        static::assertSame($redName, $red->getName());

        $greenCurrencyPrice = $green->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $greenCurrencyPrice);
        static::assertSame($greenPrice['gross'], $greenCurrencyPrice->getGross());
        static::assertSame($parentName, $green->getTranslated()['name']);
        static::assertNull($green->getName());

        /** @var array{price: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `price` FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($parentId)]);
        static::assertEquals(['c' . Defaults::CURRENCY => $parentPrice], json_decode($row['price'], true, 512, \JSON_THROW_ON_ERROR));

        /** @var array{name: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `name` FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($parentId)]);
        static::assertSame($parentName, $row['name']);

        /** @var array{price: string|null} $row */
        $row = $this->connection->fetchAssociative('SELECT `price` FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($redId)]);
        static::assertNull($row['price']);

        /** @var array{name: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `name` FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($redId)]);
        static::assertSame($redName, $row['name']);

        /** @var array{price: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `price` FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($greenId)]);
        static::assertEquals(['c' . Defaults::CURRENCY => $greenPrice], json_decode($row['price'], true, 512, \JSON_THROW_ON_ERROR));

        $row = $this->connection->fetchAssociative('SELECT * FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($greenId)]);
        static::assertEmpty($row);
    }

    public function testInsertAndUpdateInOneStep(): void
    {
        $id = Uuid::randomHex();
        $filterId = Uuid::randomHex();
        $data = [
            [
                'id' => $id,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Insert',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'test'],
                'ean' => $filterId,
            ],
            [
                'id' => $id,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Update',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 10, 'linked' => false]],
                'ean' => $filterId,
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        /** @var ProductCollection $products */
        $products = $this->repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities();
        static::assertTrue($products->has($id));

        $product = $products->get($id);

        static::assertSame('Update', $product->getName());
        $currencyPrice = $product->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $currencyPrice);
        static::assertSame(12.0, $currencyPrice->getGross());

        $count = (int) $this->connection->fetchOne('SELECT COUNT(id) FROM product WHERE ean = :filterId', ['filterId' => $filterId]);
        static::assertSame(1, $count);
    }

    public function testSwitchVariantToFullProduct(): void
    {
        $id = Uuid::randomHex();
        $child = Uuid::randomHex();

        $filterId = Uuid::randomHex();
        $data = [
            ['id' => $id, 'productNumber' => Uuid::randomHex(), 'stock' => 10, 'name' => 'Insert', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 10], 'manufacturer' => ['name' => 'test'], 'ean' => $filterId],
            ['id' => $child, 'productNumber' => Uuid::randomHex(), 'stock' => 10, 'parentId' => $id, 'name' => 'Update', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false]], 'ean' => $filterId],
        ];
        $this->repository->upsert($data, Context::createDefaultContext());

        $products = $this->repository->search(new Criteria([$id, $child]), Context::createDefaultContext());
        static::assertTrue($products->has($id));
        static::assertTrue($products->has($child));

        $raw = $this->connection->fetchAllAssociative('SELECT * FROM product WHERE ean = :filterId', ['filterId' => $filterId]);
        static::assertCount(2, $raw);

        $name = $this->connection->fetchOne('SELECT name FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($child)]);
        static::assertSame('Update', $name);

        $data = [
            [
                'id' => $child,
                'parentId' => null,
            ],
        ];

        /** @var WriteException|null $e */
        $e = null;

        try {
            $this->repository->upsert($data, Context::createDefaultContext());
        } catch (\Exception $e) {
        }

        static::assertInstanceOf(WriteException::class, $e);

        /** @var WriteConstraintViolationException $constraintViolation */
        $constraintViolation = $e->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $constraintViolation);

        static::assertSame('/taxId', $constraintViolation->getViolations()->get(0)->getPropertyPath());

        $data = [
            [
                'id' => $child,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => null,
                'name' => 'Child transformed to parent',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 13, 'net' => 12, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test3'],
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        /** @var array{parent_id: string|null} $raw */
        $raw = $this->connection->fetchAssociative('SELECT `parent_id` FROM product WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($child),
        ]);

        static::assertNull($raw['parent_id']);

        $criteria = new Criteria([$child]);
        $criteria->addAssociation('manufacturer');
        /** @var ProductCollection $products */
        $products = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertTrue($products->has($child));
        $product = $products->get($child);

        static::assertSame('Child transformed to parent', $product->getName());
        $currencyPrice = $product->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $currencyPrice);
        static::assertSame(13.0, $currencyPrice->getGross());
        $manufacturer = $product->getManufacturer();
        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturer);
        static::assertSame('test3', $manufacturer->getName());
        $tax = $product->getTax();
        static::assertInstanceOf(TaxEntity::class, $tax);
        static::assertSame(15.0, $tax->getTaxRate());
    }

    public function testSwitchVariantToFullProductWithoutName(): void
    {
        $id = Uuid::randomHex();
        $child = Uuid::randomHex();

        $data = [
            [
                'id' => $id,
                'name' => 'Insert',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'manufacturer' => ['name' => 'test'],
                'stock' => 1,
                'productNumber' => 'SW100',
            ],
            [
                'id' => $child,
                'parentId' => $id,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false]],
                'stock' => 2,
                'productNumber' => 'SW100.1',
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        $products = $this->repository->search(new Criteria([$id, $child]), Context::createDefaultContext());
        static::assertTrue($products->has($id));
        static::assertTrue($products->has($child));

        $raw = $this->connection->fetchAllAssociative(
            'SELECT * FROM product WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$id, $child])],
            ['ids' => ArrayParameterType::STRING]
        );
        static::assertCount(2, $raw);

        $name = $this->connection->fetchOne('SELECT name FROM product_translation WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($child)]);
        static::assertFalse($name);

        $data = [
            [
                'id' => $child,
                'parentId' => null,
            ],
        ];

        /** @var WriteException|null $e */
        $e = null;

        try {
            $this->repository->upsert($data, Context::createDefaultContext());
        } catch (\Exception $e) {
        }
        static::assertInstanceOf(WriteException::class, $e);

        $message = $e->getMessage();

        static::assertStringContainsString('/0/taxId', $message);
        static::assertStringContainsString('/0/stock', $message);
        static::assertStringContainsString('/0/price', $message);
        static::assertStringContainsString('/0/productNumber', $message);

        $data = [
            [
                'id' => $child,
                'parentId' => null,
                'name' => 'Child transformed to parent',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 13, 'net' => 12, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test3'],
                'stock' => 3,
                'productNumber' => 'SW200',
            ],
        ];

        $this->repository->upsert($data, Context::createDefaultContext());

        /** @var array{parent_id: string|null} $raw */
        $raw = $this->connection->fetchAssociative('SELECT `parent_id` FROM product WHERE id = :id', [
            'id' => Uuid::fromHexToBytes($child),
        ]);

        static::assertNull($raw['parent_id']);

        $criteria = new Criteria([$child]);
        $criteria->addAssociation('manufacturer');

        /** @var ProductCollection $products */
        $products = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertTrue($products->has($child));
        $product = $products->get($child);

        $currencyPrice = $product->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $currencyPrice);
        static::assertSame(13.0, $currencyPrice->getGross());
        $manufacturer = $product->getManufacturer();
        static::assertInstanceOf(ProductManufacturerEntity::class, $manufacturer);
        static::assertSame('test3', $manufacturer->getName());
        $tax = $product->getTax();
        static::assertInstanceOf(TaxEntity::class, $tax);
        static::assertSame(15.0, $tax->getTaxRate());
        static::assertSame('SW200', $product->getProductNumber());
        static::assertSame('Child transformed to parent', $product->getName());
    }

    public function testVariantInheritanceWithTax(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentTaxId = Uuid::randomHex();
        $greenTaxId = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
                ],
                'manufacturer' => ['name' => 'test'],
                'name' => 'parent',
                'tax' => ['id' => $parentTaxId, 'taxRate' => 13, 'name' => 'green'],
            ],

            //price should be inherited
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
            ],

            //name should be inherited
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
                'tax' => [
                    'id' => $greenTaxId,
                    'taxRate' => 13,
                    'name' => 'green',
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $this->repository->create($products, $context);

        $criteria = new Criteria([$redId, $greenId]);
        $criteria->addAssociation('tax');
        /** @var ProductCollection $products */
        $products = $this->repository->search($criteria, $context)->getEntities();

        $criteria = new Criteria([$parentId]);
        $criteria->addAssociation('tax');
        $context->setConsiderInheritance(false);
        /** @var ProductCollection $parents */
        $parents = $this->repository->search($criteria, $context)->getEntities();

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        $parent = $parents->get($parentId);
        $red = $products->get($redId);
        $green = $products->get($greenId);

        $parentTax = $parent->getTax();
        static::assertInstanceOf(TaxEntity::class, $parentTax);
        static::assertSame($parentTaxId, $parentTax->getId());
        $redTax = $red->getTax();
        static::assertInstanceOf(TaxEntity::class, $redTax);
        static::assertSame($parentTaxId, $redTax->getId());
        $greenTax = $green->getTax();
        static::assertInstanceOf(TaxEntity::class, $greenTax);
        static::assertSame($greenTaxId, $greenTax->getId());

        static::assertSame($parentTaxId, $parent->getTaxId());
        static::assertSame($parentTaxId, $red->getTaxId());
        static::assertSame($greenTaxId, $green->getTaxId());

        /** @var array{price: string, tax_id: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `price`, `tax_id` FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($parentId)]);

        static::assertEquals(
            [
                'c' . Defaults::CURRENCY => ['net' => 9, 'gross' => 10, 'linked' => true, 'currencyId' => Defaults::CURRENCY],
            ],
            json_decode($row['price'], true, 512, \JSON_THROW_ON_ERROR)
        );
        static::assertSame($parentTaxId, Uuid::fromBytesToHex($row['tax_id']));

        /** @var array{price: string|null, tax_id: string|null} $row */
        $row = $this->connection->fetchAssociative('SELECT `price`, `tax_id` FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($redId)]);
        static::assertNull($row['price']);
        static::assertNull($row['tax_id']);

        /** @var array{price: string|null, tax_id: string} $row */
        $row = $this->connection->fetchAssociative('SELECT `price`, `tax_id` FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($greenId)]);
        static::assertNull($row['price']);
        static::assertSame($greenTaxId, Uuid::fromBytesToHex($row['tax_id']));

        $criteria = new Criteria([$redId, $greenId]);
        $criteria->addAssociation('tax');
        $context->setConsiderInheritance(false);
        /** @var ProductCollection $products */
        $products = $this->repository->search($criteria, $context)->getEntities();
        static::assertTrue($products->has($redId));
        $red = $products->get($redId);
        static::assertNull($red->getTax());
    }

    public function testWriteProductWithSameTaxes(): void
    {
        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'];
        $price = [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]];
        $data = [
            ['productNumber' => Uuid::randomHex(), 'name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['productNumber' => Uuid::randomHex(), 'name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['productNumber' => Uuid::randomHex(), 'name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['productNumber' => Uuid::randomHex(), 'name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
            ['productNumber' => Uuid::randomHex(), 'name' => 'test', 'stock' => 10, 'tax' => $tax, 'price' => $price, 'manufacturer' => ['name' => 'test']],
        ];

        $taxes = $this->repository->create($data, Context::createDefaultContext())->getEventByEntityName(TaxDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $taxes);
        static::assertCount(1, array_unique($taxes->getIds()));
    }

    public function testProductMediaAssociationWithSortingAndPagination(): void
    {
        $id = Uuid::randomHex();
        $a = Uuid::randomHex();
        $b = Uuid::randomHex();
        $c = Uuid::randomHex();
        $d = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => $id,
            'name' => 'T-shirt',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 10,
            'media' => [
                ['id' => $d, 'position' => 4, 'media' => ['fileName' => 'd']],
                ['id' => $b, 'position' => 2, 'media' => ['fileName' => 'b']],
                ['id' => $a, 'position' => 1, 'media' => ['fileName' => 'a']],
                ['id' => $c, 'position' => 3, 'media' => ['fileName' => 'c']],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('media')
            ->setLimit(3)
            ->addSorting(new FieldSorting('product_media.position', FieldSorting::ASCENDING));

        $product = $this->repository->search($criteria, Context::createDefaultContext())
            ->first();

        $ids = $product->getMedia()->map(fn (ProductMediaEntity $a) => $a->getId());

        $order = [$a, $b, $c];
        static::assertEquals($order, array_values($ids));

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('media')
            ->setLimit(3)
            ->addSorting(new FieldSorting('product_media.position', FieldSorting::DESCENDING));

        $product = $this->repository->search($criteria, Context::createDefaultContext())
            ->first();

        $ids = $product->getMedia()->map(fn (ProductMediaEntity $a) => $a->getId());

        $order = [$d, $c, $b];
        static::assertEquals($order, array_values($ids));
    }

    public function testVariantInheritanceWithMedia(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentMediaId = Uuid::randomHex();
        $greenMediaId = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'name' => 'T-shirt',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'stock' => 10,
                'media' => [
                    [
                        'id' => $parentMediaId,
                        'media' => [
                            'id' => $parentMediaId,
                            'name' => 'test file',
                        ],
                    ],
                ],
            ],
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $parentId,
                'name' => 'red',
                'stock' => 10,
            ],
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $parentId,
                'stock' => 10,
                'name' => 'green',
                'media' => [
                    [
                        'id' => $greenMediaId,
                        'media' => [
                            'id' => $greenMediaId,
                            'name' => 'test file',
                        ],
                    ],
                ],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria([$redId, $greenId]);
        $criteria->addAssociation('media');

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        /** @var ProductCollection $products */
        $products = $this->repository->search($criteria, $context)->getEntities();

        $criteria = new Criteria([$parentId]);
        $criteria->addAssociation('media');

        /** @var ProductCollection $parents */
        $parents = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        $parent = $parents->get($parentId);
        $green = $products->get($greenId);
        $red = $products->get($redId);

        $parentMedia = $parent->getMedia();
        static::assertInstanceOf(ProductMediaCollection::class, $parentMedia);
        static::assertCount(1, $parentMedia);
        static::assertTrue($parentMedia->has($parentMediaId));

        $greenMedia = $green->getMedia();
        static::assertInstanceOf(ProductMediaCollection::class, $greenMedia);
        static::assertCount(1, $greenMedia);
        static::assertTrue($greenMedia->has($greenMediaId));

        $redMedia = $red->getMedia();
        static::assertInstanceOf(ProductMediaCollection::class, $redMedia);
        static::assertCount(1, $redMedia);
        static::assertTrue($redMedia->has($parentMediaId));

        /** @var array{media_id: string} $row */
        $row = $this->connection->fetchAssociative('SELECT media_id FROM product_media WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($parentId)]);
        static::assertSame($parentMediaId, Uuid::fromBytesToHex($row['media_id']));

        /** @var array{media_id: string}|false $row */
        $row = $this->connection->fetchAssociative('SELECT media_id FROM product_media WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($redId)]);
        static::assertFalse($row);

        /** @var array{media_id: string} $row */
        $row = $this->connection->fetchAssociative('SELECT media_id FROM product_media WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($greenId)]);
        static::assertSame($greenMediaId, Uuid::fromBytesToHex($row['media_id']));
    }

    public function testActiveInheritance(): void
    {
        $ids = new IdsCollection();

        $products = [
            [
                'id' => $ids->create('parent'),
                'productNumber' => Uuid::randomHex(),
                'name' => 'T-shirt',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'stock' => 10,
            ],
            [
                'id' => $ids->create('red'),
                'productNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'name' => 'red',
                'stock' => 10,
            ],
            [
                'id' => $ids->create('green'),
                'productNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'name' => 'green',
                'active' => false,
            ],
            [
                'id' => $ids->create('blue'),
                'productNumber' => Uuid::randomHex(),
                'parentId' => $ids->get('parent'),
                'stock' => 10,
                'name' => 'green',
                'active' => true,
            ],
        ];

        $context = Context::createDefaultContext();
        $this->getContainer()
            ->get('product.repository')
            ->create($products, $context);

        $context->setConsiderInheritance(true);

        $criteria = new Criteria($ids->getList(['red', 'green', 'blue']));
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var IdSearchResult $products */
        $products = $this->getContainer()
            ->get('product.repository')
            ->searchIds($criteria, $context);

        static::assertTrue($products->has($ids->get('red')));
        static::assertTrue($products->has($ids->get('blue')));
        static::assertFalse($products->has($ids->get('green')));
    }

    public function testVariantInheritanceWithCategories(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentCategory = Uuid::randomHex();
        $greenCategory = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'T-shirt',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $parentCategory, 'name' => 'parent'],
                ],
            ],
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
                'name' => 'red',
            ],
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
                'name' => 'green',
                'categories' => [
                    ['id' => $greenCategory, 'name' => 'green'],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $this->repository->create($products, $context);

        $criteria = new Criteria([$redId, $greenId]);
        $criteria->addAssociation('categories');
        /** @var ProductCollection $products */
        $products = $this->repository->search($criteria, $context)->getEntities();

        $criteria = new Criteria([$parentId]);
        $criteria->addAssociation('categories');
        /** @var ProductCollection $parents */
        $parents = $this->repository->search($criteria, $context)->getEntities();

        static::assertTrue($parents->has($parentId));
        static::assertTrue($products->has($redId));
        static::assertTrue($products->has($greenId));

        $parent = $parents->get($parentId);
        $green = $products->get($greenId);
        $red = $products->get($redId);

        $parentCategories = $parent->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $parentCategories);
        static::assertSame([$parentCategory], array_values($parentCategories->getIds()));
        $redCategories = $red->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $redCategories);
        static::assertSame([$parentCategory], array_values($redCategories->getIds()));
        $greenCategories = $green->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $greenCategories);
        static::assertSame([$greenCategory], array_values($greenCategories->getIds()));

        /** @var array{category_tree: string, categories: string} $row */
        $row = $this->connection->fetchAssociative('SELECT category_tree, categories FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($parentId)]);
        static::assertContains($parentCategory, json_decode($row['category_tree'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame($parentId, Uuid::fromBytesToHex($row['categories']));

        /** @var array{category_tree: string, categories: string} $row */
        $row = $this->connection->fetchAssociative('SELECT category_tree, categories FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($redId)]);
        static::assertContains($parentCategory, json_decode($row['category_tree'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame($parentId, Uuid::fromBytesToHex($row['categories']));

        /** @var array{category_tree: string, categories: string} $row */
        $row = $this->connection->fetchAssociative('SELECT category_tree, categories FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($greenId)]);
        static::assertContains($greenCategory, json_decode($row['category_tree'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame($greenId, Uuid::fromBytesToHex($row['categories']));
    }

    public function testSearchByInheritedName(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $parentName,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'price' => [$parentPrice],
            ],

            //price should be inherited
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
            ],

            //name should be inherited
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [$greenPrice],
                'parentId' => $parentId,
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $this->repository->create($products, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.name', $parentName));

        $products = $this->repository->search($criteria, $context);
        static::assertCount(2, $products);
        static::assertTrue($products->has($parentId));
        static::assertTrue($products->has($greenId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.name', $redName));

        $products = $this->repository->search($criteria, $context);
        static::assertCount(1, $products);
        static::assertTrue($products->has($redId));
    }

    public function testSearchByInheritedPrice(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $manufacturerId = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => $parentName,
                'price' => [$parentPrice],
            ],

            //price should be inherited
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
            ],

            //name should be inherited
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [$greenPrice],
                'parentId' => $parentId,
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);
        $this->repository->create($products, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.price', $parentPrice['gross']));
        $criteria->addFilter(new EqualsFilter('product.manufacturerId', $manufacturerId));

        $products = $this->repository->search($criteria, $context);
        static::assertCount(2, $products);
        static::assertTrue($products->has($parentId));
        static::assertTrue($products->has($redId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.price', $greenPrice['gross']));

        $products = $this->repository->search($criteria, $context);
        static::assertCount(1, $products);
        static::assertTrue($products->has($greenId));
    }

    public function testSearchCategoriesWithProductsUseInheritance(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $categoryId = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => $parentName,
                'price' => [$parentPrice],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
            ],

            //price should be inherited
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
            ],

            //name should be inherited
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [$greenPrice],
                'parentId' => $parentId,
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);
        $this->repository->create($products, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.products.price', $greenPrice['gross']));

        $repository = $this->getContainer()->get('category.repository');
        $categories = $repository->searchIds($criteria, $context);

        static::assertSame(1, $categories->getTotal());
        static::assertContains($categoryId, $categories->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new AndFilter([
            new EqualsFilter('category.products.price', $parentPrice['gross']),
            new EqualsFilter('category.products.parentId', null),
        ]));

        $repository = $this->getContainer()->get('category.repository');
        $categories = $repository->searchIds($criteria, $context);

        static::assertSame(1, $categories->getTotal());
        static::assertContains($categoryId, $categories->getIds());
    }

    public function testSearchProductsOverInheritedCategories(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $redCategories = [
            ['id' => $redId, 'name' => 'Red category'],
        ];

        $parentCategories = [
            ['id' => $parentId, 'name' => 'Parent category'],
        ];

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'Parent',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'categories' => $parentCategories,
            ],
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => 'Red',
                'parentId' => $parentId,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'categories' => $redCategories,
            ],

            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'parentId' => $parentId,
            ],
        ];

        $this->repository->upsert($products, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.products.name', 'Parent'));

        $repo = $this->getContainer()->get('category.repository');
        $result = $repo->search($criteria, $this->context);
        static::assertCount(1, $result);
        static::assertTrue($result->has($parentId));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.products.name', 'Red'));
        $result = $repo->search($criteria, $this->context);
        static::assertCount(1, $result);
        static::assertTrue($result->has($redId));
    }

    public function testSearchManufacturersWithProductsUseInheritance(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $parentPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false];
        $parentName = 'T-shirt';
        $greenPrice = ['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false];
        $redName = 'Red shirt';

        $manufacturerId = Uuid::randomHex();
        $manufacturerId2 = Uuid::randomHex();

        $products = [
            [
                'id' => $parentId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => $parentName,
                'price' => [$parentPrice],
                'manufacturer' => [
                    'id' => $manufacturerId,
                    'name' => 'test',
                ],
            ],
            //price should be inherited
            [
                'id' => $redId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'name' => $redName,
                'parentId' => $parentId,
                'manufacturer' => [
                    'id' => $manufacturerId2,
                    'name' => 'test',
                ],
            ],
            //manufacturer should be inherited
            [
                'id' => $greenId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 10,
                'price' => [$greenPrice],
                'parentId' => $parentId,
            ],
        ];

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $this->repository->create($products, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_manufacturer.products.price', $greenPrice['gross']));

        $result = $this->getContainer()->get('product_manufacturer.repository')->searchIds($criteria, $context);

        static::assertSame(1, $result->getTotal());
        static::assertContains($manufacturerId, $result->getIds());
    }

    public function testWriteProductOverCategories(): void
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $categories = [
            [
                'id' => $categoryId,
                'name' => 'Cat1',
                'products' => [
                    [
                        'id' => $productId,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 10,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'test',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                        'manufacturer' => ['name' => 'test'],
                    ],
                ],
            ],
        ];

        $repository = $this->getContainer()->get('category.repository');

        $repository->create($categories, Context::createDefaultContext());

        $products = $this->repository->search(new Criteria([$productId]), Context::createDefaultContext());

        static::assertCount(1, $products);
        static::assertTrue($products->has($productId));

        /** @var ProductEntity $product */
        $product = $products->get($productId);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertIsArray($product->getCategoryTree());
        static::assertContains($categoryId, $product->getCategoryTree());
    }

    public function testWriteProductOverManufacturer(): void
    {
        $productId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();

        $manufacturers = [
            [
                'id' => $manufacturerId,
                'name' => 'Manufacturer',
                'products' => [
                    [
                        'id' => $productId,
                        'productNumber' => Uuid::randomHex(),
                        'stock' => 10,
                        'name' => 'test',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'manufacturerId' => $manufacturerId,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $repository = $this->getContainer()->get('product_manufacturer.repository');

        $repository->create($manufacturers, Context::createDefaultContext());

        $products = $this->repository->search(new Criteria([$productId]), Context::createDefaultContext());

        static::assertCount(1, $products);
        static::assertTrue($products->has($productId));

        /** @var ProductEntity $product */
        $product = $products->get($productId);

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame($manufacturerId, $product->getManufacturerId());
    }

    public function testCreateAndAssignProductProperty(): void
    {
        $id = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $blueId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'name' => 'test',
            'stock' => 10,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'properties' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => 'color'],
                ],
                [
                    'id' => $blueId,
                    'name' => 'blue',
                    'groupId' => $colorId,
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('properties');
        /** @var ProductEntity $product */
        $product = $this->repository->search($criteria, Context::createDefaultContext())->get($id);

        $sheet = $product->getProperties();

        static::assertInstanceOf(PropertyGroupOptionCollection::class, $sheet);
        static::assertCount(2, $sheet);

        static::assertTrue($sheet->has($redId));
        static::assertTrue($sheet->has($blueId));

        $blue = $sheet->get($blueId);
        $red = $sheet->get($redId);

        static::assertSame('red', $red->getName());
        static::assertSame('blue', $blue->getName());

        static::assertSame($colorId, $red->getGroupId());
        static::assertSame($colorId, $blue->getGroupId());
    }

    public function testCreateAndAssignProductOption(): void
    {
        $id = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $blueId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'test',
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'options' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => $colorId],
                ],
                [
                    'id' => $blueId,
                    'name' => 'blue',
                    'groupId' => $colorId,
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('options');
        /** @var ProductEntity $product */
        $product = $this->repository->search($criteria, Context::createDefaultContext())->get($id);

        $sheet = $product->getOptions();

        static::assertInstanceOf(PropertyGroupOptionCollection::class, $sheet);
        static::assertCount(2, $sheet);

        static::assertTrue($sheet->has($redId));
        static::assertTrue($sheet->has($blueId));

        $blue = $sheet->get($blueId);
        $red = $sheet->get($redId);

        static::assertSame('red', $red->getName());
        static::assertSame('blue', $blue->getName());

        static::assertSame($colorId, $red->getGroupId());
        static::assertSame($colorId, $blue->getGroupId());
    }

    public function testCreateAndAssignProductConfigurator(): void
    {
        $id = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $blueId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'name' => 'test',
            'stock' => 10,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'configuratorSettings' => [
                [
                    'id' => $redId,
                    'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => $colorId],
                    ],
                ],
                [
                    'id' => $blueId,
                    'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 90, 'linked' => false],
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
            ],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('configuratorSettings.option');
        /** @var ProductEntity $product */
        $product = $this->repository->search($criteria, Context::createDefaultContext())->get($id);

        $configuratorSettings = $product->getConfiguratorSettings();

        static::assertInstanceOf(ProductConfiguratorSettingCollection::class, $configuratorSettings);
        static::assertCount(2, $configuratorSettings);

        static::assertTrue($configuratorSettings->has($redId));
        static::assertTrue($configuratorSettings->has($blueId));

        $blue = $configuratorSettings->get($blueId);
        $red = $configuratorSettings->get($redId);

        static::assertEquals(['net' => 25, 'gross' => 50, 'linked' => false, 'currencyId' => Defaults::CURRENCY], $red->getPrice());
        static::assertEquals(['net' => 90, 'gross' => 100, 'linked' => false, 'currencyId' => Defaults::CURRENCY], $blue->getPrice());

        $redOption = $red->getOption();
        static::assertInstanceOf(PropertyGroupOptionEntity::class, $redOption);
        static::assertSame('red', $redOption->getName());
        $blueOption = $blue->getOption();
        static::assertInstanceOf(PropertyGroupOptionEntity::class, $blueOption);
        static::assertSame('blue', $blueOption->getName());

        static::assertSame($colorId, $redOption->getGroupId());
        static::assertSame($colorId, $blueOption->getGroupId());
    }

    public function testCreateAndAssignCMSPage(): void
    {
        $id = Uuid::randomHex();

        $cmsPageId = Uuid::randomHex();

        $this->getContainer()->get('cms_page.repository')->create(
            [
                [
                    'id' => $cmsPageId,
                    'type' => 'product_detail',
                ],
            ],
            $this->context
        );

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'name' => 'test',
            'stock' => 10,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'cmsPageId' => $cmsPageId,
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('cmsPage');
        /** @var ProductEntity $product */
        $product = $this->repository->search($criteria, Context::createDefaultContext())->get($id);
        static::assertSame($cmsPageId, $product->getCmsPageId());
        $cmsPage = $product->getCmsPage();
        static::assertInstanceOf(CmsPageEntity::class, $cmsPage);
        static::assertEquals('product_detail', $cmsPage->getType());
    }

    public function testModifyProductPriceMatrix(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $id,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->repository->create([$data], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(1, $product->getPrices());

        /** @var ProductPriceEntity $price */
        $price = $product->getPrices()->first();
        static::assertSame($ruleA, $price->getRuleId());

        $data = [
            'id' => $id,
            'prices' => [
                //update existing rule with new price and quantity end to add another graduation
                [
                    'id' => $id,
                    'quantityEnd' => 20,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 5000, 'net' => 4000, 'linked' => false],
                    ],
                ],

                //add new graduation to existing rule
                [
                    'quantityStart' => 21,
                    'ruleId' => $ruleA,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 50, 'linked' => false],
                    ],
                ],
            ],
        ];

        $this->repository->upsert([$data], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(2, $product->getPrices());

        /** @var ProductPriceEntity $price */
        $price = $product->getPrices()->get($id);

        static::assertSame($ruleA, $price->getRuleId());
        static::assertInstanceOf(PriceCollection::class, $price->getPrice());

        static::assertEquals(
            new Price(Defaults::CURRENCY, 4000, 5000, false),
            $price->getPrice()->getCurrencyPrice(Defaults::CURRENCY)
        );

        static::assertSame(1, $price->getQuantityStart());
        static::assertSame(20, $price->getQuantityEnd());

        $id3 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'prices' => [
                [
                    'id' => $id3,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 50, 'linked' => false]],
                ],
            ],
        ];

        $this->repository->upsert([$data], $context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('prices');

        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($id));

        /** @var ProductEntity $product */
        $product = $products->get($id);

        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(3, $product->getPrices());

        /** @var ProductPriceEntity $price */
        $price = $product->getPrices()->get($id3);
        static::assertSame($ruleB, $price->getRuleId());

        static::assertEquals(
            new Price(Defaults::CURRENCY, 50, 50, false),
            $price->getPrice()->getCurrencyPrice(Defaults::CURRENCY)
        );

        static::assertSame(1, $price->getQuantityStart());
        static::assertNull($price->getQuantityEnd());
    }

    public function testWriteProductCategoriesWithoutId(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'name' => 'product',
            'stock' => 10,
            'ean' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'manufacturer'],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
            'categories' => [
                ['name' => 'category_name'],
            ],
        ];
        $this->connection->executeStatement('DELETE FROM sales_channel');
        $this->connection->executeStatement('DELETE FROM category');

        $this->repository->create([$data], Context::createDefaultContext());

        $count = $this->connection->fetchAllAssociative('SELECT * FROM category');

        static::assertCount(1, $count, print_r($count, true));
    }

    public function testDuplicateProductNumber(): void
    {
        $productNumber = 'sw1' . Uuid::randomHex();

        $data = [
            'id' => Uuid::randomHex(),
            'productNumber' => $productNumber,
            'name' => 'product',
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'manufacturer'],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
        ];

        $this->repository->create([$data], Context::createDefaultContext());

        $data = [
            'id' => Uuid::randomHex(),
            'productNumber' => $productNumber,
            'name' => 'product',
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'manufacturer'],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
        ];

        $this->expectException(DuplicateProductNumberException::class);
        $this->expectExceptionMessage('Product with number "' . $productNumber . '" already exists.');

        $this->repository->create([$data], Context::createDefaultContext());
    }

    public function testPriceSortingWithDecimalPrecision(): void
    {
        $defaults = [
            'name' => 'product',
            'stock' => 10,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'tax', 'taxRate' => 15],
        ];

        $ids = new TestDataCollection();

        $data = [
            array_replace_recursive($defaults, ['id' => $ids->create('a'), 'price' => [['gross' => 99.96]], 'productNumber' => $ids->get('a')]),
            array_replace_recursive($defaults, ['id' => $ids->create('b'), 'price' => [['gross' => 99.92]], 'productNumber' => $ids->get('b')]),
            array_replace_recursive($defaults, ['id' => $ids->create('c'), 'price' => [['gross' => 99.95]], 'productNumber' => $ids->get('c')]),
            array_replace_recursive($defaults, ['id' => $ids->create('d'), 'price' => [['gross' => 99.91]], 'productNumber' => $ids->get('d')]),
        ];

        $this->repository->create($data, Context::createDefaultContext());

        $criteria = new Criteria($ids->all());
        $criteria->addSorting(new FieldSorting('price'));

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

        static::assertEquals(
            array_values($ids->getList(['d', 'b', 'c', 'a'])),
            $result->getIds()
        );

        $criteria = new Criteria($ids->all());
        $criteria->addSorting(new FieldSorting('price', 'DESC'));

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

        static::assertEquals(
            array_values($ids->getList(['a', 'c', 'b', 'd'])),
            $result->getIds()
        );
    }

    public function testPriceSortingWithDifferentCurrencyNoFallback(): void
    {
        $ids = new TestDataCollection();
        $isoCode = 'DEM';
        $currencyFactor = 0.5;
        $ids->create($isoCode);

        $this->getContainer()->get('currency.repository')->create(
            [
                [
                    'id' => $ids->get($isoCode),
                    'factor' => $currencyFactor,
                    'shortName' => 'test',
                    'name' => 'name',
                    'symbol' => 'DM',
                    'isoCode' => $isoCode,
                    'decimalPrecision' => 2,
                    'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                    'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                ],
            ],
            Context::createDefaultContext()
        );

        $data = [
            (new ProductBuilder($ids, 'a'))->price(99.94, null, 'default', 99.94)->build(),
            (new ProductBuilder($ids, 'b'))->price(15, 10)->price(99.93, null, $isoCode, 99.93)->build(),
            (new ProductBuilder($ids, 'c'))->price(15, 10)->price(99.97, null, $isoCode, 99.97)->build(),
            (new ProductBuilder($ids, 'd'))->price(15, 10)->price(99.91, null, $isoCode, 99.91)->build(),
            (new ProductBuilder($ids, 'e'))->price(15, 10)->price(99.95, null, $isoCode, 99.95)->build(),
        ];

        $this->repository->create($data, Context::createDefaultContext());

        foreach (['', '.listPrice'] as $priceType) {
            $criteria = new Criteria($ids->all());
            $criteria->addSorting(new FieldSorting(sprintf('price.%s%s.gross', $ids->get($isoCode), $priceType)));

            $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

            static::assertEquals(
                array_values($ids->getList(['a', 'd', 'b', 'e', 'c'])),
                $result->getIds()
            );

            $criteria = new Criteria($ids->all());
            $criteria->addSorting(new FieldSorting(sprintf('price.%s%s.gross', $ids->get($isoCode), $priceType), 'DESC'));

            $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

            static::assertEquals(
                array_values($ids->getList(['c', 'e', 'b', 'd', 'a'])),
                $result->getIds()
            );
        }

        // test context with currency id
        $context = new Context(new SystemSource(), [], $ids->get($isoCode), [Defaults::LANGUAGE_SYSTEM], Defaults::LIVE_VERSION, $currencyFactor);

        $criteria = new Criteria($ids->all());
        $criteria->addSorting(new FieldSorting('price'));

        $result = $this->repository->searchIds($criteria, $context);

        static::assertEquals(
            array_values($ids->getList(['a', 'd', 'b', 'e', 'c'])),
            $result->getIds()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function customFieldVariantsProvider(): array
    {
        return [
            'Test own values' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent'],
                    'child' => ['foo' => 'child'],
                ],
                self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test merged with parent' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent', 'bar' => 'parent'],
                    'child' => ['foo' => 'child', 'bar' => 'parent'],
                ],
                self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test not merged with parent, no inheritance' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent', 'bar' => 'parent'],
                    'child' => ['foo' => 'child'],
                ],
                self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], false),
            ],
            'Test inheritance child null value' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent'],
                    'child' => ['foo' => 'parent'],
                ],
                self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test child null value no inheritance' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent'],
                    'child' => [],
                ],
                self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], false),
            ],
            'Test child and parent null value no inheritance' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                    ],
                ],
                [
                    'parent' => [],
                    'child' => [],
                ],
                self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], false),
            ],
            'Test only parent null value with inheritance' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                    ],
                ],
                [
                    'parent' => [],
                    'child' => ['foo' => 'child'],
                ],
                self::createLanguageContext([Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test inheritance with language chain' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'child translated']],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent translated'],
                    'child' => ['foo' => 'child translated'],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test inheritance with language chain merged with parent' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent translated'],
                    'child' => ['foo' => 'parent translated'],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test inheritance with language chain no translation for language' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent'],
                    'child' => ['foo' => 'child'],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test inheritance with language chain no translation for language and child at all' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent'],
                    'child' => ['foo' => 'parent'],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test language chain without inheritance' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent'],
                    'child' => [],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], false),
            ],
            'Test language chain without inheritance but language is set' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent translated'],
                    'child' => [],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], false),
            ],
            'Test language chain without inheritance but language is set, main is not' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => null], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent translated'],
                    'child' => [],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], false),
            ],
            'Test language chain without inheritance and only main language set' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => null]],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent'],
                    'child' => ['foo' => 'child'],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], false),
            ],
            'Test language with inheritance and merge with parent and languages' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'child translated']],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent translated', 'bar' => 'parent'],
                    'child' => ['foo' => 'child translated', 'bar' => 'parent'],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test language with inheritance and merge with parent and languages, child own values' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'child translated', 'bar' => 'child translated']],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent translated', 'bar' => 'parent'],
                    'child' => ['foo' => 'child translated', 'bar' => 'child translated'],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test language with inheritance and merge with parent and languages, main child has values' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated']],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child', 'bar' => 'child'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'child translated']],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent translated', 'bar' => 'parent'],
                    'child' => ['foo' => 'child translated', 'bar' => 'child'],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
            ],
            'Test language with inheritance and merge with parent and languages, main child has values and parent language has values' => [
                [
                    'parent' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'parent', 'bar' => 'parent'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'parent translated', 'bar' => 'parent translated']],
                    ],
                    'child' => [
                        Defaults::LANGUAGE_SYSTEM => ['customFields' => ['foo' => 'child', 'bar' => 'child'], 'name' => 'A'],
                        self::TEST_LANGUAGE_ID => ['customFields' => ['foo' => 'child translated']],
                    ],
                ],
                [
                    'parent' => ['foo' => 'parent translated', 'bar' => 'parent translated'],
                    'child' => ['foo' => 'child translated', 'bar' => 'parent translated'],
                ],
                self::createLanguageContext([self::TEST_LANGUAGE_ID, Defaults::LANGUAGE_SYSTEM], true),
            ],
        ];
    }

    /**
     * @dataProvider customFieldVariantsProvider
     *
     * @group slow
     *
     * @param array<string, mixed> $translations
     * @param array<string, mixed> $expected
     */
    public function testVariantCustomFieldInheritance(array $translations, array $expected, Context $context): void
    {
        $ids = new TestDataCollection();

        $products = [
            [
                'id' => $ids->create('parent'),
                'name' => 'Insert',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 10],
                'stock' => 1,
                'productNumber' => Uuid::randomHex(),
                'translations' => $translations['parent'],
            ],
            [
                'id' => $ids->create('child'),
                'parentId' => $ids->get('parent'),
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 12, 'net' => 11, 'linked' => false]],
                'stock' => 2,
                'productNumber' => Uuid::randomHex(),
                'translations' => $translations['child'],
            ],
        ];

        $this->createLanguage(self::TEST_LANGUAGE_ID);

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria($ids->getList(['parent', 'child']));

        /** @var ProductCollection $products */
        $products = $this->repository->search($criteria, $context)->getEntities();

        foreach ($expected as $key => $customFields) {
            $id = $ids->get('parent');
            if ($key === 'child') {
                $id = $ids->get('child');
            }

            static::assertTrue($products->has($id));
            $translation = $products->get($id)->getTranslation('customFields');
            static::assertEquals($customFields, $translation);
        }
    }

    public function testChildren(): void
    {
        $rootId = 'f1d2554b0ce847cd82f3ac9bd1c0dfca';
        $data = [
            'id' => $rootId,
            'name' => 'Variant product',
            'productNumber' => 'TEST',
            'price' => [
                [
                    'gross' => 111,
                    'net' => 111,
                    'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    'linked' => false,
                ],
            ],
            'stock' => 1234,
            'tax' => [
                'taxRate' => 0,
                'name' => 'foo',
            ],
            'manufacturer' => [
                'id' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                'name' => 'Test variant manufacturer',
            ],
            'manufacturerId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            'properties' => [
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfba',
                    'name' => 'red',
                    'colorHexCode' => '#ff0000',
                    'group' => [
                        'id' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                        'name' => 'color',
                        'displayType' => 'color',
                    ],
                ],
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbb',
                    'name' => 'green',
                    'colorHexCode' => '#00ff00',
                    'groupId' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                ],
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbc',
                    'name' => 'blue',
                    'colorHexCode' => '#0000ff',
                    'groupId' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                ],
            ],
            'children' => [
                [
                    'productNumber' => 'TEST.1',
                    'stock' => 10,
                    'options' => [
                        ['id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfba'],
                    ],
                ],
                [
                    'productNumber' => 'TEST.2',
                    'stock' => 10,
                    'options' => [
                        ['id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbb'],
                    ],
                ],
                [
                    'productNumber' => 'TEST.3',
                    'stock' => 10,
                    'options' => [
                        ['id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbc'],
                    ],
                ],
            ],
            'configuratorSettings' => [
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfaa',
                    'optionId' => 'f1d2554b0ce847cd82f3ac9bd1c0dfba',
                ],
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfab',
                    'optionId' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbb',
                ],
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfac',
                    'optionId' => 'f1d2554b0ce847cd82f3ac9bd1c0dfbc',
                ],
            ],
        ];

        $this->repository->upsert([$data], Context::createDefaultContext());
        $criteria = new Criteria([$rootId]);
        $criteria->addAssociation('configuratorSettings');
        /** @var ProductEntity $result */
        $result = $this->repository->search($criteria, Context::createDefaultContext())->first();

        static::assertInstanceOf(ProductConfiguratorSettingCollection::class, $result->getConfiguratorSettings());
        static::assertCount(3, $result->getConfiguratorSettings());
    }

    public function testUpdateDescriptionToBeNull(): void
    {
        $id = Uuid::randomHex();
        $description = 'My name is Product Test';

        $data = [
            'id' => $id,
            'name' => 'test',
            'description' => $description,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->repository->create([$data], $this->context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        static::assertSame($description, $product->getDescription());

        $this->repository->update([
            ['description' => null, 'id' => $id],
        ], $this->context);

        /** @var ProductEntity $product */
        $product = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->get($id);

        static::assertNull($product->getDescription());
    }

    public function testUpdatePropertyIdsForVariantsWhenUpdateFromParents(): void
    {
        $context = Context::createDefaultContext();

        $productId = Uuid::randomHex();
        $variantA = Uuid::randomHex();
        $variantB = Uuid::randomHex();
        $propertyIds = [
            'f1d2554b0ce847cd82f3ac9bd1c0dfba',
            'f1d2554b0ce847cd82f3ac9bd1c0dfbb',
            'f1d2554b0ce847cd82f3ac9bd1c0dfbc',
        ];

        $data = [
            'id' => $productId,
            'name' => 'Master product',
            'productNumber' => 'TEST',
            'price' => [
                [
                    'gross' => 111,
                    'net' => 111,
                    'currencyId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                    'linked' => false,
                ],
            ],
            'stock' => 1234,
            'tax' => [
                'taxRate' => 0,
                'name' => 'foo',
            ],
            'manufacturer' => [
                'id' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                'name' => 'Test variant manufacturer',
            ],
            'manufacturerId' => 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            'properties' => [
                [
                    'id' => $propertyIds[0],
                    'name' => 'red',
                    'colorHexCode' => '#ff0000',
                    'group' => [
                        'id' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                        'name' => 'color',
                        'displayType' => 'color',
                    ],
                ],
                [
                    'id' => $propertyIds[1],
                    'name' => 'green',
                    'colorHexCode' => '#00ff00',
                    'groupId' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                ],
            ],
            'children' => [
                [
                    'productNumber' => 'TEST.1',
                    'id' => $variantA,
                    'stock' => 10,
                    'options' => [
                        ['id' => $propertyIds[0]],
                    ],
                ],
                [
                    'productNumber' => 'TEST.2',
                    'id' => $variantB,
                    'stock' => 10,
                    'options' => [
                        ['id' => $propertyIds[1]],
                    ],
                ],
            ],
            'configuratorSettings' => [
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfaa',
                    'optionId' => $propertyIds[0],
                ],
                [
                    'id' => 'f1d2554b0ce847cd82f3ac9bd1c0dfab',
                    'optionId' => $propertyIds[1],
                ],
            ],
        ];

        $this->repository->upsert([$data], $context);

        /** @var ProductCollection $variants */
        $variants = $this->repository->search(new Criteria([$variantB, $variantA]), $context)->getEntities();
        $product = $this->repository->search(new Criteria([$productId]), $context)->first();

        static::assertCount(2, $variants);
        static::assertInstanceOf(ProductEntity::class, $product);

        $productProperties = $product->getPropertyIds();
        static::assertTrue($variants->has($variantA));
        $variantAProperties = $variants->get($variantA)->getPropertyIds();
        static::assertTrue($variants->has($variantB));
        $variantBProperties = $variants->get($variantB)->getPropertyIds();

        static::assertIsArray($productProperties);
        sort($productProperties);
        static::assertIsArray($variantAProperties);
        sort($variantAProperties);
        static::assertIsArray($variantBProperties);
        sort($variantBProperties);

        static::assertEquals($productProperties, $variantAProperties);
        static::assertEquals($productProperties, $variantBProperties);

        $data = [
            'properties' => [
                [
                    'id' => $propertyIds[2],
                    'name' => 'green',
                    'colorHexCode' => '#00ff00',
                    'groupId' => 'adf2554b0ce847cd82f3ac9bd1c0dfba',
                ],
            ],
            'id' => $productId,
        ];

        $this->repository->upsert([$data], $this->context);

        // depending ids (parents and children) will be queued
        $this->runWorker();

        /** @var ProductCollection $variants */
        $variants = $this->repository->search(new Criteria([$variantB, $variantA]), $context)->getEntities();
        $product = $this->repository->search(new Criteria([$productId]), $context)->first();

        static::assertCount(2, $variants);
        static::assertInstanceOf(ProductEntity::class, $product);

        $productProperties = $product->getPropertyIds();
        static::assertTrue($variants->has($variantA));
        $variantAProperties = $variants->get($variantA)->getPropertyIds();
        static::assertTrue($variants->has($variantB));
        $variantBProperties = $variants->get($variantB)->getPropertyIds();

        static::assertIsArray($productProperties);
        sort($productProperties);
        static::assertIsArray($variantAProperties);
        sort($variantAProperties);
        static::assertIsArray($variantBProperties);
        sort($variantBProperties);

        static::assertEquals($productProperties, $variantAProperties);
        static::assertEquals($productProperties, $variantBProperties);
    }

    public function testInheritanceUpdateOnDeleteRelation(): void
    {
        $ids = new IdsCollection();

        $product = (new ProductBuilder($ids, 'x1'))->price(100)->category('c1')->build();
        $this->getContainer()->get('product.repository')->upsert([$product], Context::createDefaultContext());

        $event = $this->getContainer()->get('category.repository')->delete([['id' => $ids->get('c1')]], Context::createDefaultContext());

        $expected = [
            'category.deleted' => [$ids->get('c1')],
            'product_category.deleted' => [['productId' => $ids->get('x1'), 'categoryId' => $ids->get('c1')]],
            'product_category_tree.deleted' => [['productId' => $ids->get('x1'), 'categoryId' => $ids->get('c1')]],
            'product.written' => [$ids->get('x1')],
        ];

        foreach ($expected as $key => $value) {
            static::assertArrayHasKey($key, $event->getList());
            static::assertEquals($value, $event->getList()[$key]);
        }
    }

    /**
     * @param non-empty-array<string> $languages
     */
    private static function createLanguageContext(array $languages, bool $inheritance): Context
    {
        return new Context(new SystemSource(), [], Defaults::CURRENCY, $languages, Defaults::LIVE_VERSION, 1.0, $inheritance);
    }

    /**
     * @param array<string> $ruleIds
     */
    private function createContext(array $ruleIds = []): Context
    {
        return new Context(new SystemSource(), $ruleIds);
    }

    private function createLanguage(string $id, ?string $parentId = Defaults::LANGUAGE_SYSTEM): void
    {
        $languageRepository = $this->getContainer()->get('language.repository');

        $languageRepository->upsert(
            [
                [
                    'id' => $id,
                    'name' => sprintf('name-%s', $id),
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'parentId' => $parentId,
                    'translationCode' => [
                        'id' => self::TEST_LOCALE_ID,
                        'code' => self::TEST_LANGUAGE_LOCALE_CODE,
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                    'salesChannels' => [
                        ['id' => TestDefaults::SALES_CHANNEL],
                    ],
                    'salesChannelDefaultAssignments' => [
                        ['id' => TestDefaults::SALES_CHANNEL],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }
}
