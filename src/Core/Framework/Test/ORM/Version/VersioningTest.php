<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Version;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\Metadata\Metadata;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\RangeQuery;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Test\TaxFixtures;

class VersioningTest extends TestCase
{
    use IntegrationTestBehaviour, TaxFixtures;

    /**
     * @var RepositoryInterface
     */
    private $taxRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    /**
     * @var RepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * @var RepositoryInterface
     */
    private $shippingMethodRepository;

    /**
     * @var RepositoryInterface
     */
    private $mediaRepository;

    public function setUp()
    {
        $this->taxRepository = $this->getContainer()->get('tax.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->shippingMethodRepository = $this->getContainer()->get('shipping_method.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testVersionChangeOnInsert(): void
    {
        $uuid = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $taxData = [
            'id' => $uuid,
            'name' => 'foo tax',
            'taxRate' => 20,
            'tenantId' => Defaults::TENANT_ID,
        ];

        $this->taxRepository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes, sprintf('Change for entity_id "%s" was not created.', $uuid));

        $change = array_shift($changes);

        $taxData['versionId'] = Defaults::LIVE_VERSION;

        $payload = json_decode($change['payload'], true);
        unset($payload['createdAt']);
        static::assertEquals($taxData, $payload);
    }

    public function testVersionChangeOnInsertWithSubresources(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $manufacturerId = Uuid::uuid4()->getHex();
        $taxId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $product = [
            'id' => $productId,
            'name' => 'parent',
            'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
            'manufacturer' => ['id' => $manufacturerId, 'name' => 'manufacturer test'],
            'tax' => ['id' => $taxId, 'taxRate' => 18, 'name' => 'test'],
        ];

        $this->productRepository->create([$product], $context);
        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, Defaults::LIVE_VERSION);

        static::assertCount(1, $changes);

        $productChanges = [
            'id' => $productId,
            'versionId' => Defaults::LIVE_VERSION,
            'tenantId' => Defaults::TENANT_ID,
            'catalogId' => Defaults::CATALOG,
            'parentVersionId' => Defaults::LIVE_VERSION,
            'manufacturerId' => $manufacturerId,
            'productManufacturerVersionId' => Defaults::LIVE_VERSION,
            'unitVersionId' => Defaults::LIVE_VERSION,
            'taxId' => $taxId,
            'taxVersionId' => Defaults::LIVE_VERSION,
            'price' => [
                'gross' => 10, 'net' => 9, 'linked' => 0,
            ],
            'isCloseout' => 0,
            'purchaseSteps' => 1,
            'minPurchase' => 1,
            'shippingFree' => 0,
            'sales' => 0,
            'minDeliveryTime' => 1,
            'maxDeliveryTime' => 2,
            'restockTime' => 1,
        ];
        $payload = json_decode($changes[0]['payload'], true);
        unset($payload['createdAt']);
        static::assertEquals($productChanges, $payload);

        $changes = $this->getVersionData(ProductManufacturerDefinition::getEntityName(), $manufacturerId, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $manufacturerChanges = [
            'id' => $manufacturerId,
            'tenantId' => Defaults::TENANT_ID,
            'versionId' => Defaults::LIVE_VERSION,
            'catalogId' => Defaults::CATALOG,
            'mediaVersionId' => Defaults::LIVE_VERSION,
        ];
        $payload = json_decode($changes[0]['payload'], true);
        unset($payload['createdAt']);
        static::assertEquals($manufacturerChanges, $payload);

        $changes = $this->getTranslationVersionData(ProductManufacturerTranslationDefinition::getEntityName(), Defaults::LANGUAGE_EN, 'productManufacturerId', $manufacturerId, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $manufacturerTranslationChange = [
            'productManufacturerId' => $manufacturerId,
            'productManufacturerVersionId' => Defaults::LIVE_VERSION,
            'name' => 'manufacturer test',
            'languageId' => Defaults::LANGUAGE_EN,
            'catalogId' => Defaults::CATALOG,
        ];
        $payload = json_decode($changes[0]['payload'], true);
        unset($payload['createdAt']);

        static::assertEquals($manufacturerTranslationChange, $payload);
    }

    public function testCreateNewVersion(): void
    {
        $uuid = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $taxId = $this->getTaxNineteenPercent()->getId();
        $versionId = $this->taxRepository->createVersion($taxId, $context, 'testCreateVersionWithoutRelations version');

        static::assertNotEmpty($versionId);

        $versionId = Uuid::fromString($versionId);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :versionId',
            [
                'id' => Uuid::fromString($taxId)->getBytes(),
                'versionId' => $versionId->getBytes(),
            ]
        );

        static::assertNotFalse($tax, 'Tax clone was not created.');

        static::assertEquals(Uuid::fromString($taxId)->getBytes(), $tax['id']);
        static::assertEquals($versionId->getBytes(), $tax['version_id']);
        static::assertEquals('NineteenPercentTax', $tax['name']);
        static::assertEquals(19, $tax['tax_rate']);
    }

    public function testCreateNewVersionWithSubresources(): void
    {
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $shippingMethodId = Uuid::uuid4();
        $priceId = Uuid::uuid4();

        $methodData = [
            'id' => $shippingMethodId->getHex(),
            'bindShippingfree' => false,
            'name' => 'foo',
            'type' => 1,
            'prices' => [
                [
                    'id' => $priceId->getHex(),
                    'quantityFrom' => 10,
                    'price' => 10.0,
                    'factor' => 1.0,
                ],
            ],
        ];

        $this->shippingMethodRepository->create([$methodData], $context);

        $versionId = $this->shippingMethodRepository->createVersion($shippingMethodId->getHex(), $context, 'testCreateVersionWithSubresources version');

        static::assertNotEmpty($versionId);

        $versionId = Uuid::fromString($versionId);

        $method = $this->connection->fetchAssoc(
            'SELECT * FROM shipping_method WHERE id = :id AND version_id = :versionId',
            [
                'id' => $shippingMethodId->getBytes(),
                'versionId' => $versionId->getBytes(),
            ]
        );

        $prices = $this->connection->fetchAll(
            'SELECT * FROM shipping_method_price WHERE shipping_method_id = :id AND version_id = :versionId',
            [
                'id' => $shippingMethodId->getBytes(),
                'versionId' => $versionId->getBytes(),
            ]
        );

        static::assertNotFalse($method, 'Tax clone was not created.');
        static::assertCount(1, $prices, 'Product clones were not created.');

        static::assertEquals($shippingMethodId->getBytes(), $method['id']);
        static::assertEquals($versionId->getBytes(), $method['version_id']);
        static::assertEquals($methodData['type'], $method['type']);

        static::assertEquals($shippingMethodId->getBytes(), $prices[0]['shipping_method_id']);
        static::assertEquals($versionId->getBytes(), $prices[0]['version_id']);
    }

    public function testMergeVersions(): void
    {
        $uuid = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $taxData = ['id' => $uuid->getHex(), 'name' => 'foo tax', 'taxRate' => 20];
        $this->taxRepository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $versionId = $this->taxRepository->createVersion($uuid->getHex(), $context, 'testMerge version');
        $versionId = Uuid::fromString($versionId);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId->getHex());
        static::assertCount(1, $changes);
        static::assertEquals('clone', $changes[0]['action']);

        $versionContext = $context->createWithVersionId($versionId->getHex());
        $this->taxRepository->update([['id' => $uuid->getHex(), 'name' => 'new merged name']], $versionContext);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
        ]);
        static::assertEquals('foo tax', $row['name']);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId->getHex());
        static::assertCount(2, $changes);
        static::assertEquals('clone', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);

        $this->taxRepository->merge($versionId->getHex(), $context);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
        ]);

        static::assertEquals('new merged name', $row['name']);

        $row = $this->connection->fetchAssoc('SELECT * FROM version WHERE id = :id', ['id' => $versionId->getBytes()]);
        static::assertEmpty($row);

        $row = $this->connection->fetchAssoc('SELECT * FROM version_commit WHERE version_id = :id', ['id' => $versionId->getBytes()]);
        static::assertEmpty($row);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId->getHex());
        static::assertEmpty($changes);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), Defaults::LIVE_VERSION);
        static::assertCount(2, $changes);

        static::assertEquals('insert', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);
    }

    public function testReadConsiderVersion(): void
    {
        $uuid = Uuid::uuid4();
        $liveVersionContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $taxData = ['id' => $uuid->getHex(), 'name' => 'foo tax', 'taxRate' => 20];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $versionId = $this->taxRepository->createVersion($uuid->getHex(), $liveVersionContext, 'testMerge version');
        $versionId = Uuid::fromString($versionId);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId->getHex());
        static::assertCount(1, $changes);
        static::assertEquals('clone', $changes[0]['action']);

        $versionContext = $liveVersionContext->createWithVersionId($versionId->getHex());
        $this->taxRepository->update([['id' => $uuid->getHex(), 'name' => 'new merged name']], $versionContext);

        $basic = $this->taxRepository->read(new ReadCriteria([$uuid->getHex()]), $liveVersionContext);
        static::assertCount(1, $basic);
        static::assertTrue($basic->has($uuid->getHex()));
        $tax = $basic->get($uuid->getHex());
        static::assertEquals('foo tax', $tax->getName());

        $basic = $this->taxRepository->read(new ReadCriteria([$uuid->getHex()]), $versionContext);
        static::assertCount(1, $basic);
        static::assertTrue($basic->has($uuid->getHex()));
        $tax = $basic->get($uuid->getHex());
        static::assertEquals('new merged name', $tax->getName());

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
        ]);
        static::assertEquals('foo tax', $row['name']);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId->getHex());
        static::assertCount(2, $changes);
        static::assertEquals('clone', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);

        $this->taxRepository->merge($versionId->getHex(), $liveVersionContext);

        $basic = $this->taxRepository->read(new ReadCriteria([$uuid->getHex()]), $liveVersionContext);
        static::assertCount(1, $basic);
        static::assertTrue($basic->has($uuid->getHex()));
        $tax = $basic->get($uuid->getHex());
        static::assertEquals('new merged name', $tax->getName());

        $basic = $this->taxRepository->read(new ReadCriteria([$uuid->getHex()]), $versionContext);
        static::assertCount(1, $basic);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString($versionId)->getBytes(),
        ]);
        static::assertEmpty($row);
    }

    public function testSearcherConsidersVersionFallback(): void
    {
        $uuid = Uuid::uuid4();
        $liveVersionContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $taxData = ['id' => $uuid->getHex(), 'name' => 'foo tax', 'taxRate' => 5];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $versionId = $this->taxRepository->createVersion($uuid->getHex(), $liveVersionContext, 'testMerge version');
        $versionId = Uuid::fromString($versionId);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => $versionId->getBytes(),
        ]);
        static::assertNotEmpty($tax);
        static::assertEquals(5, $tax['tax_rate']);

        $versionContext = $liveVersionContext->createWithVersionId($versionId->getHex());
        $this->taxRepository->update([['id' => $uuid->getHex(), 'name' => 'new merged name', 'taxRate' => 4]], $versionContext);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => $versionId->getBytes(),
        ]);
        static::assertNotEmpty($tax);
        static::assertEquals(4, $tax['tax_rate']);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('tax.taxRate', 4));

        $result = $this->taxRepository->searchIds($criteria, $liveVersionContext);
        static::assertEquals(0, $result->getTotal());

        $result = $this->taxRepository->searchIds($criteria, $versionContext);
        static::assertEquals(1, $result->getTotal());

        $taxData = ['name' => 'foo tax', 'taxRate' => 4];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $result = $this->taxRepository->searchIds($criteria, $versionContext);
        static::assertEquals(2, $result->getTotal());

        $result = $this->taxRepository->searchIds($criteria, $liveVersionContext);
        static::assertEquals(1, $result->getTotal());
    }

    public function testOneToManyVersioning(): void
    {
        $uuid = Uuid::uuid4();

        $methodData = [
            'id' => $uuid->getHex(),
            'bindShippingfree' => false,
            'name' => 'foo',
            'type' => 1,
            'prices' => [
                [
                    'id' => $uuid->getHex(),
                    'quantityFrom' => 10,
                    'price' => 10.0,
                    'factor' => 1.0,
                ],
            ],
        ];

        $liveVersionContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->shippingMethodRepository->create([$methodData], $liveVersionContext);

        $changes = $this->getVersionData(ShippingMethodDefinition::getEntityName(), $uuid->getHex(), Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $changes = $this->getVersionData(ShippingMethodPriceDefinition::getEntityName(), $uuid->getHex(), Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $versionId = $this->shippingMethodRepository->createVersion($uuid->getHex(), $liveVersionContext);

        $changes = $this->getVersionData(ShippingMethodDefinition::getEntityName(), $uuid->getHex(), $versionId);
        static::assertCount(1, $changes);

        $changes = $this->getVersionData(ShippingMethodPriceDefinition::getEntityName(), $uuid->getHex(), $versionId);
        static::assertCount(1, $changes);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);

        $this->shippingMethodRepository->upsert([
            [
                'id' => $uuid->getHex(),
                'type' => 2,
                'prices' => [
                    ['id' => $uuid->getHex(), 'price' => 15.0],
                ],
            ],
        ], $versionContext);

        $changes = $this->getVersionData(ShippingMethodDefinition::getEntityName(), $uuid->getHex(), $versionId);
        static::assertCount(2, $changes);

        $changes = $this->getVersionData(ShippingMethodPriceDefinition::getEntityName(), $uuid->getHex(), $versionId);
        static::assertCount(2, $changes);

        $criteria = new ReadCriteria([$uuid->getHex()]);
        $criteria->addAssociation('prices');

        $liveShippingMethod = $this->shippingMethodRepository->read($criteria, $liveVersionContext);
        static::assertCount(1, $liveShippingMethod);
        static::assertTrue($liveShippingMethod->has($uuid->getHex()));
        $shippingMethod = $liveShippingMethod->get($uuid->getHex());

        /* @var ShippingMethodStruct $shippingMethod */
        static::assertEquals(1, $shippingMethod->getType());
        static::assertCount(1, $shippingMethod->getPrices());
        static::assertEquals(10.0, $shippingMethod->getPrices()->get($uuid->getHex())->getPrice());

        $criteria = new ReadCriteria([$uuid->getHex()]);
        $criteria->addAssociation('prices');
        $versionShippingMethod = $this->shippingMethodRepository->read($criteria, $versionContext);
        static::assertCount(1, $versionShippingMethod);
        static::assertTrue($versionShippingMethod->has($uuid->getHex()));
        $shippingMethod = $versionShippingMethod->get($uuid->getHex());

        /* @var ShippingMethodStruct $shippingMethod */
        static::assertEquals(2, $shippingMethod->getType());
        static::assertCount(1, $shippingMethod->getPrices());
        static::assertEquals(15.0, $shippingMethod->getPrices()->get($uuid->getHex())->getPrice());

        $this->shippingMethodRepository->merge($versionId, $liveVersionContext);

        $liveShippingMethod = $this->shippingMethodRepository->read($criteria, $liveVersionContext);
        static::assertCount(1, $liveShippingMethod);
        static::assertTrue($liveShippingMethod->has($uuid->getHex()));
        $shippingMethod = $liveShippingMethod->get($uuid->getHex());

        /* @var ShippingMethodStruct $shippingMethod */
        static::assertEquals(2, $shippingMethod->getType());
        static::assertCount(1, $shippingMethod->getPrices());
        static::assertEquals(15.0, $shippingMethod->getPrices()->get($uuid->getHex())->getPrice());

        $liveShippingMethod = $this->shippingMethodRepository->read($criteria, $versionContext);
        static::assertCount(1, $liveShippingMethod);
        static::assertTrue($liveShippingMethod->has($uuid->getHex()));
        $shippingMethod = $liveShippingMethod->get($uuid->getHex());

        /* @var ShippingMethodStruct $shippingMethod */
        static::assertEquals(2, $shippingMethod->getType());
        static::assertCount(1, $shippingMethod->getPrices());
        static::assertEquals(15.0, $shippingMethod->getPrices()->get($uuid->getHex())->getPrice());
    }

    public function testVersioningWithProductInheritance(): void
    {
        $productId = Uuid::uuid4();
        $variantId = Uuid::uuid4();

        $products = [
            [
                'id' => $productId->getHex(),
                'name' => 'parent',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
            ],
            [
                'id' => $variantId->getHex(),
                'price' => ['gross' => 15, 'net' => 14, 'linked' => false],
                'parentId' => $productId->getHex(),
            ],
        ];
        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->productRepository->create($products, $liveContext);

        $variantVersionId = $this->productRepository->createVersion($variantId->getHex(), $liveContext);
        $versionContext = $liveContext->createWithVersionId($variantVersionId);

        $this->productRepository->update([
            ['id' => $variantId->getHex(), 'price' => ['gross' => 20, 'net' => 19, 'linked' => false]],
        ], $versionContext);

        $variant = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => $variantId->getBytes(),
            'version' => Uuid::fromString($variantVersionId)->getBytes(),
        ]);

        static::assertEquals(['gross' => 20, 'net' => 19, 'linked' => false], json_decode($variant['price'], true));

        $variants = $this->productRepository->read(new ReadCriteria([$variantId->getHex()]), $versionContext);
        static::assertCount(1, $variants);
        static::assertTrue($variants->has($variantId->getHex()));

        $variant = $variants->get($variantId->getHex());
        static::assertEquals(new PriceStruct(19, 20, false), $variant->getPrice());
        static::assertEquals('parent', $variant->getName());

        $this->productRepository->createVersion($productId->getHex(), $liveContext, 'test parent', $variantVersionId);

        $this->productRepository->update([
            ['id' => $productId->getHex(), 'name' => 'parent version', 'price' => ['gross' => 25, 'net' => 24]],
        ], $versionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId->getHex(), $variantVersionId);
        static::assertCount(2, $changes);

        $changes = $this->getTranslationVersionData(ProductTranslationDefinition::getEntityName(), Defaults::LANGUAGE_EN, 'productId', $productId->getHex(), $variantVersionId);
        static::assertCount(2, $changes);

        $product = $this->connection->fetchAssoc(
            'SELECT * FROM product_translation WHERE product_id = :id AND product_version_id = :version AND language_id = :language',
            [
                'id' => $productId->getBytes(),
                'version' => Uuid::fromString($variantVersionId)->getBytes(),
                'language' => Uuid::fromString($versionContext->getLanguageId())->getBytes(),
            ]
        );
        static::assertEquals('parent version', $product['name']);

        $variants = $this->productRepository->read(new ReadCriteria([$productId->getHex()]), $versionContext);
        static::assertCount(1, $variants);
        static::assertTrue($variants->has($productId->getHex()));

        $variant = $variants->get($productId->getHex());
        static::assertEquals(25, $variant->getPrice()->getGross());
        static::assertEquals('parent version', $variant->getName());

        $variants = $this->productRepository->read(new ReadCriteria([$variantId->getHex()]), $versionContext);
        static::assertCount(1, $variants);
        static::assertTrue($variants->has($variantId->getHex()));

        $variant = $variants->get($variantId->getHex());
        static::assertEquals(20, $variant->getPrice()->getGross());
        static::assertEquals('parent version', $variant->getName());
    }

    public function testTaxRestrictions(): void
    {
        $id = Uuid::uuid4();

        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);

        $this->taxRepository->create([['id' => $id->getHex(), 'name' => 'test', 'taxRate' => 15]], $liveContext);

        $this->productRepository->create([
            [
                'id' => $id->getHex(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'taxId' => $id->getHex(),
                'manufacturer' => ['name' => 'test'],
            ],
        ], $liveContext);

        $versionId = $this->taxRepository->createVersion($id->getHex(), $liveContext);

        $versionContext = $liveContext->createWithVersionId($versionId);

        $this->taxRepository->update([
            ['id' => $id->getHex(), 'taxRate' => 19],
        ], $versionContext);

        $this->taxRepository->merge($versionId, $liveContext);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $id->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
        ]);

        static::assertEquals(19, $tax['tax_rate']);
    }

    public function testMergeBoolField(): void
    {
        static::markTestSkipped('The versioning cant handle updates with BoolField - issue NEXT-670 needs to be fixed first');
        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $parentCategoryId = $this->createCategory($liveContext);

        $categoryId = Uuid::uuid4()->getHex();
        $versionId = Uuid::uuid4()->getHex();

        $categories = [
            [
                'id' => $categoryId,
                'parentId' => $parentCategoryId,
                'name' => 'TEST cat',
                'active' => true,
            ],
        ];
        $this->categoryRepository->create($categories, $liveContext);

        $this->categoryRepository->createVersion($categoryId, $liveContext, 'boolVersionUpdate', $versionId);

        $versionContext = $liveContext->createWithVersionId($versionId);

        $update = ['id' => $categoryId, 'active' => false];
        $this->categoryRepository->update([$update], $versionContext);

        // This call fails, because the "merge"-call tries to convert the serialized number 0/1 to boolean (also see NEXT-670)
        $this->categoryRepository->merge($versionId, $liveContext);

        $category = $this->connection->fetchAssoc('SELECT * FROM category WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromString($categoryId)->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
        ]);

        static::assertEquals(0, $category['active']);

        $fetchedCategory = $this->categoryRepository->read(new ReadCriteria([$categoryId]), $liveContext)->get($categoryId);
        static::assertEquals(false, $fetchedCategory->get('active'));
    }

    public function testMergeDateTimeField(): void
    {
        static::markTestSkipped('The versioning cannot handle DateTimes - issue NEXT-670 needs to be fixed first');

        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);

        $customerId = Uuid::uuid4()->getHex();
        $versionId = Uuid::uuid4()->getHex();

        $address = [
            'firstName' => 'not',
            'lastName' => 'nope',
            'city' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'salutation' => 'not',
            'country' => ['name' => 'not'],
        ];

        $customer = [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => $address,
                'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::uuid4()->getHex() . '@example.com',
                'password' => 'not',
                'lastName' => 'not',
                'firstName' => 'nope',
                'salutation' => 'not',
                'customerNumber' => 'not',
                'lastLogin' => new \DateTime(),
            ];

        $this->customerRepository->create([$customer], $liveContext);
        $this->customerRepository->createVersion($customerId, $liveContext, 'dateVersionUpdate', $versionId);

        $versionContext = $liveContext->createWithVersionId($versionId);
        $updateTime = (new \DateTime())->add(new \DateInterval('P2Y4DT6H8M'));
        $update = ['id' => $customerId, 'lastLogin' => $updateTime];
        $this->customerRepository->update([$update], $versionContext);

        $this->customerRepository->merge($versionId, $liveContext);

        $fetchedCategory = $this->customerRepository->read(new ReadCriteria([$customerId]), $liveContext)->get($customerId);
        static::assertEquals($updateTime, $fetchedCategory->getLastLogin());
    }

    public function testMergeCalculatedField(): void
    {
        static::markTestSkipped('The versioning does recalculate calculated fields after a merge - issue NEXT-670 needs to be fixed first');

        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);

        $categories = [
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'catOld1',
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'catOld2',
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'catNew1',
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'catNew2',
            ],
        ];

        $this->categoryRepository->create($categories, $liveContext);

        $versionId = Uuid::uuid4()->getHex();
        $productId = Uuid::uuid4()->getHex();
        $product = [
            'id' => $productId,
            'name' => 'Test',
            'price' => ['gross' => 10, 'net' => 9],
            'manufacturer' => ['id' => Uuid::uuid4()->getHex(), 'name' => 'test'],
            'tax' => ['id' => Uuid::uuid4()->getHex(), 'taxRate' => 17, 'name' => 'with id'],
            'categoryTree' => [$categories[0]['id'], $categories[1]['id']],
        ];

        // Assign the first two categories to the product
        $this->productRepository->create([$product], $liveContext);

        $fetchedProduct = $this->productRepository->read(new ReadCriteria([$productId]), $liveContext)->get($productId);
        $oldCategories = $fetchedProduct->getCategoryTree();
        static::assertEquals(2, \count($oldCategories));
        static::assertContains($categories[0]['id'], $oldCategories);
        static::assertContains($categories[1]['id'], $oldCategories);

        $fetchedCat = $this->categoryRepository->read(new ReadCriteria([$categories[0]['id']]), $liveContext)->get($categories[0]['id']);
        static::assertEquals($categories[0]['name'], $fetchedCat->getName());

        $this->productRepository->createVersion($productId, $liveContext, 'calcFieldVersionUpdate', $versionId);
        $versionContext = $liveContext->createWithVersionId($versionId);

        $update = [
            'id' => $productId,
            'categories' => [['id' => $categories[2]['id']], ['id' => $categories[3]['id']]],
        ];
        // In the new version of the product, switch the the "old" cateogories with the "new" ones
        $this->productRepository->update([$update], $versionContext);
        $this->productRepository->merge($versionId, $liveContext);

        $fetchedProductUpdated = $this->productRepository->read(new ReadCriteria([$productId]), $liveContext)->get($productId);
        $updatedCategories = $fetchedProductUpdated->getCategoryTree();

        // This fails because of NEXT-670.
        static::assertEquals(2, \count($updatedCategories));

        static::assertContains($categories[2]['id'], $updatedCategories);
        static::assertContains($categories[3]['id'], $updatedCategories);
    }

    public function testMergeMediaItems(): void
    {
        static::markTestSkipped('The versioning does handle permissions correctly - issue NEXT-670 needs to be fixed first');

        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $liveContext->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $mediaId = Uuid::uuid4()->getHex();
        $mediaData = [
            'id' => $mediaId,
            'name' => 'test_media',
            'extension' => '.jpg',
        ];

        $this->mediaRepository->create([$mediaData], $liveContext);
        $versionId = Uuid::uuid4()->getHex();
        $this->mediaRepository->createVersion($mediaId, $liveContext, 'mediaVersionUpdate', $versionId);
        $versionContext = $liveContext->createWithVersionId($versionId);

        $metadata = new Metadata();
        $metadata->setRawMetadata(['generic test data']);
        $update = [
            'id' => $mediaId,
            'mimeType' => 'image/jpg',
        ];
        // This fails because of NEXT-670.
        $this->mediaRepository->update([$update], $versionContext);
        $this->mediaRepository->merge($versionId, $liveContext);

        $fetchedUpdatedMedia = $this->mediaRepository->read(new ReadCriteria([$mediaId]), $liveContext)->get($mediaId);
        static::assertEquals('image/jpg', $fetchedUpdatedMedia->getMimeType());
    }

    public function testMergeNestedObjects(): void
    {
        static::markTestSkipped('The versioning cannot merge ListFields corretly - issue NEXT-670 needs to be fixed first');

        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $liveContext->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $mediaId = Uuid::uuid4()->getHex();
        $mediaData = [
            'id' => $mediaId,
            'name' => 'test_media',
            'metaData' => null,
        ];

        $this->mediaRepository->create([$mediaData], $liveContext);

        $fetchedMedia = $this->mediaRepository->read(new ReadCriteria([$mediaId]), $liveContext)->get($mediaId);
        static::assertEquals(null, $fetchedMedia->getMetaData());

        $versionId = Uuid::uuid4()->getHex();
        $this->mediaRepository->createVersion($mediaId, $liveContext, 'listFieldVersionUpdate', $versionId);
        $versionContext = $liveContext->createWithVersionId($versionId);

        $metadata = new Metadata();
        $metadata->setRawMetadata(['generic test data']);
        $update = [
            'id' => $mediaId,
            'metaData' => $metadata,
        ];
        $this->mediaRepository->update([$update], $versionContext);
        $this->mediaRepository->merge($versionId, $liveContext);

        $fetchedUpdatedMedia = $this->mediaRepository->read(new ReadCriteria([$mediaId]), $liveContext)->get($mediaId);
        // This fails because of NEXT-670.
        static::assertEquals($metadata, $fetchedUpdatedMedia->getMetaData()->getRawMetadata());
    }

    public function testCampaign(): void
    {
        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);

        $parentCategoryId = $this->createCategory($liveContext);

        $product1 = Uuid::uuid4();
        $product2 = Uuid::uuid4();

        $category = Uuid::uuid4()->getHex();
        $versionId = Uuid::uuid4()->getHex();

        $taxId1 = Uuid::uuid4();
        $taxId2 = Uuid::uuid4();

        $products = [
            [
                'id' => $product1->getHex(),
                'name' => 'product test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $taxId1->getHex(), 'name' => 'test', 'taxRate' => 7],
                'categories' => [
                    ['id' => $category, 'parentId' => $parentCategoryId, 'name' => 'TEST cat'],
                ],
            ], [
                'id' => $product2->getHex(),
                'name' => 'product test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $taxId2->getHex(), 'name' => 'test', 'taxRate' => 7],
                'categories' => [
                    ['id' => $category],
                ],
            ],
        ];

        $this->productRepository->create($products, $liveContext);

        $this->productRepository->createVersion($product1->getHex(), $liveContext, 'Campaign', $versionId);

        $versionContext = $liveContext->createWithVersionId($versionId);
        $update = ['id' => $product1->getHex(), 'tax' => ['id' => $taxId1->getHex(), 'name' => 'test', 'taxRate' => 19]];
        $this->productRepository->update([$update], $versionContext);

        $versionId = $this->productRepository->createVersion($product2->getHex(), $liveContext, 'Campaign', $versionId);

        $versionContext = $liveContext->createWithVersionId($versionId);
        $update = ['id' => $product2->getHex(), 'tax' => ['id' => $taxId2->getHex(), 'name' => 'test', 'taxRate' => 25]];
        $this->productRepository->update([$update], $versionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1->getHex(), $versionId);
        static::assertCount(2, $changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->getHex(), $versionId);
        static::assertCount(2, $changes);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.categories.id', $category));
        $criteria->addFilter(new RangeQuery('product.tax.taxRate', [RangeQuery::GTE => 19]));

        $search = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1->getHex(), $search->getIds());
        static::assertContains($product2->getHex(), $search->getIds());

        $notExisting = Uuid::uuid4()->getHex();
        $notExistingContext = $versionContext->createWithVersionId($notExisting);

        $search = $this->productRepository->searchIds($criteria, $notExistingContext);
        static::assertCount(0, $search->getIds());

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        static::assertCount(0, $search->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.categories.id', $category));
        $criteria->addFilter(new TermQuery('product.tax.taxRate', 7));

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1->getHex(), $search->getIds());
        static::assertContains($product2->getHex(), $search->getIds());

        $search = $this->productRepository->searchIds($criteria, $notExistingContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1->getHex(), $search->getIds());
        static::assertContains($product2->getHex(), $search->getIds());

        //MERGE
        $this->productRepository->merge($versionId, $liveContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1->getHex(), $versionId);
        static::assertEmpty($changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->getHex(), $versionId);
        static::assertEmpty($changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1->getHex(), Defaults::LIVE_VERSION);
        static::assertCount(2, $changes);
        static::assertEquals('insert', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->getHex(), Defaults::LIVE_VERSION);
        static::assertCount(2, $changes);
        static::assertEquals('insert', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->getHex(), $versionId);
        static::assertEmpty($changes);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :version',
            ['id' => $taxId1->getBytes(), 'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()]
        );
        static::assertArraySubset(['name' => 'test', 'tax_rate' => 19], $tax);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :version',
            ['id' => $taxId2->getBytes(), 'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()]
        );
        static::assertArraySubset(['name' => 'test', 'tax_rate' => 25], $tax);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.categories.id', $category));
        $criteria->addFilter(new RangeQuery('product.tax.taxRate', [RangeQuery::GTE => 19]));

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1->getHex(), $search->getIds());
        static::assertContains($product2->getHex(), $search->getIds());
    }

    private function getVersionData(string $entity, string $id, string $versionId): array
    {
        return $this->connection->fetchAll(
            "SELECT d.* 
             FROM version_commit_data d
             INNER JOIN version_commit c
               ON c.id = d.version_commit_id
               AND c.version_id = :version
               AND c.tenant_id = d.tenant_id
             WHERE entity_name = :entity 
             AND d.tenant_id = :tenant
             AND JSON_EXTRACT(entity_id, '$.id') = :id
             ORDER BY auto_increment",
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromString($versionId)->getBytes(),
                'tenant' => Uuid::fromString(Defaults::TENANT_ID)->getBytes(),
            ]
        );
    }

    private function getTranslationVersionData(string $entity, string $languageId, string $foreignKeyName, string $foreignKey, string $versionId): array
    {
        return $this->connection->fetchAll(
            "SELECT * 
             FROM version_commit_data 
             WHERE entity_name = :entity
             AND tenant_id = :tenant 
             AND JSON_EXTRACT(entity_id, '$." . $foreignKeyName . "') = :id
             AND JSON_EXTRACT(entity_id, '$.languageId') = :language
             AND JSON_EXTRACT(entity_id, '$.versionId') = :version
             ORDER BY auto_increment",
            [
                'entity' => $entity,
                'id' => $foreignKey,
                'language' => $languageId,
                'version' => $versionId,
                'tenant' => Uuid::fromString(Defaults::TENANT_ID)->getBytes(),
            ]
        );
    }

    private function createCategory(Context $context, array $override = []): string
    {
        $id = Uuid::uuid4();
        $payload = array_merge(
            [
                'id' => $id->getHex(),
                'name' => 'Random category name',
                'catalogId' => $context->getCatalogIds()[0],
            ],
            $override
        );

        $this->getContainer()->get('category.repository')->create([$payload], $context);

        return $payload['id'];
    }
}
