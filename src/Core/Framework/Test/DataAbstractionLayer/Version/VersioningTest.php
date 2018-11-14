<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Version;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerStruct;
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
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Struct\Uuid;
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
                'gross' => 10,
                'net' => 9,
                'linked' => 0,
            ],
            'isCloseout' => false,
            'purchaseSteps' => 1,
            'minPurchase' => 1,
            'shippingFree' => false,
            'sales' => 0,
            'minDeliveryTime' => 1,
            'maxDeliveryTime' => 2,
            'restockTime' => 1,
        ];
        $payload = json_decode($changes[0]['payload'], true);
        unset($payload['createdAt']);
        unset($payload['price']['_class']);
        unset($payload['price']['extensions']);
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
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $taxId = $this->getTaxNineteenPercent()->getId();
        $versionId = $this->taxRepository->createVersion($taxId, $context, 'testCreateVersionWithoutRelations version');

        static::assertNotEmpty($versionId);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :versionId',
            [
                'id' => Uuid::fromStringToBytes($taxId),
                'versionId' => Uuid::fromHexToBytes($versionId),
            ]
        );

        static::assertNotFalse($tax, 'Tax clone was not created.');

        static::assertEquals(Uuid::fromHexToBytes($taxId), $tax['id']);
        static::assertEquals(Uuid::fromHexToBytes($versionId), $tax['version_id']);
        static::assertEquals('NineteenPercentTax', $tax['name']);
        static::assertEquals(19, $tax['tax_rate']);
    }

    public function testCreateNewVersionWithSubresources(): void
    {
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $shippingMethodId = Uuid::uuid4()->getHex();
        $priceId = Uuid::uuid4()->getHex();

        $methodData = [
            'id' => $shippingMethodId,
            'bindShippingfree' => false,
            'name' => 'foo',
            'type' => 1,
            'prices' => [
                [
                    'id' => $priceId,
                    'quantityFrom' => 10,
                    'price' => 10.0,
                    'factor' => 1.0,
                ],
            ],
        ];

        $this->shippingMethodRepository->create([$methodData], $context);

        $versionId = $this->shippingMethodRepository->createVersion($shippingMethodId, $context, 'testCreateVersionWithSubresources version');

        static::assertNotEmpty($versionId);

        $method = $this->connection->fetchAssoc(
            'SELECT * FROM shipping_method WHERE id = :id AND version_id = :versionId',
            [
                'id' => Uuid::fromHexToBytes($shippingMethodId),
                'versionId' => Uuid::fromHexToBytes($versionId),
            ]
        );

        $prices = $this->connection->fetchAll(
            'SELECT * FROM shipping_method_price WHERE shipping_method_id = :id AND version_id = :versionId',
            [
                'id' => Uuid::fromHexToBytes($shippingMethodId),
                'versionId' => Uuid::fromHexToBytes($versionId),
            ]
        );

        static::assertNotFalse($method, 'Tax clone was not created.');
        static::assertCount(1, $prices, 'Product clones were not created.');

        static::assertEquals(Uuid::fromHexToBytes($shippingMethodId), $method['id']);
        static::assertEquals(Uuid::fromHexToBytes($versionId), $method['version_id']);
        static::assertEquals($methodData['type'], $method['type']);

        static::assertEquals(Uuid::fromHexToBytes($shippingMethodId), $prices[0]['shipping_method_id']);
        static::assertEquals(Uuid::fromHexToBytes($versionId), $prices[0]['version_id']);
    }

    public function testMergeVersions(): void
    {
        $uuid = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $taxData = ['id' => $uuid, 'name' => 'foo tax', 'taxRate' => 20];
        $this->taxRepository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $versionId = $this->taxRepository->createVersion($uuid, $context, 'testMerge version');

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, $versionId);
        static::assertCount(1, $changes);
        static::assertEquals('clone', $changes[0]['action']);

        $versionContext = $context->createWithVersionId($versionId);
        $this->taxRepository->update([['id' => $uuid, 'name' => 'new merged name']], $versionContext);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($uuid),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);
        static::assertEquals('foo tax', $row['name']);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, $versionId);
        static::assertCount(2, $changes);
        static::assertEquals('clone', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);

        $this->taxRepository->merge($versionId, $context);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($uuid),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        static::assertEquals('new merged name', $row['name']);

        $row = $this->connection->fetchAssoc('SELECT * FROM version WHERE id = :id', ['id' => Uuid::fromHexToBytes($versionId)]);
        static::assertEmpty($row);

        $row = $this->connection->fetchAssoc('SELECT * FROM version_commit WHERE version_id = :id', ['id' => Uuid::fromHexToBytes($versionId)]);
        static::assertEmpty($row);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, $versionId);
        static::assertEmpty($changes);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        static::assertCount(2, $changes);

        static::assertEquals('insert', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);
    }

    public function testReadConsiderVersion(): void
    {
        $uuid = Uuid::uuid4()->getHex();
        $liveVersionContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $taxData = ['id' => $uuid, 'name' => 'foo tax', 'taxRate' => 20];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $versionId = $this->taxRepository->createVersion($uuid, $liveVersionContext, 'testMerge version');

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, $versionId);
        static::assertCount(1, $changes);
        static::assertEquals('clone', $changes[0]['action']);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);
        $this->taxRepository->update([['id' => $uuid, 'name' => 'new merged name']], $versionContext);

        $basic = $this->taxRepository->read(new ReadCriteria([$uuid]), $liveVersionContext);
        static::assertCount(1, $basic);
        static::assertTrue($basic->has($uuid));
        $tax = $basic->get($uuid);
        static::assertEquals('foo tax', $tax->getName());

        $basic = $this->taxRepository->read(new ReadCriteria([$uuid]), $versionContext);
        static::assertCount(1, $basic);
        static::assertTrue($basic->has($uuid));
        $tax = $basic->get($uuid);
        static::assertEquals('new merged name', $tax->getName());

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($uuid),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);
        static::assertEquals('foo tax', $row['name']);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, $versionId);
        static::assertCount(2, $changes);
        static::assertEquals('clone', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);

        $this->taxRepository->merge($versionId, $liveVersionContext);

        $basic = $this->taxRepository->read(new ReadCriteria([$uuid]), $liveVersionContext);
        static::assertCount(1, $basic);
        static::assertTrue($basic->has($uuid));
        $tax = $basic->get($uuid);
        static::assertEquals('new merged name', $tax->getName());

        $basic = $this->taxRepository->read(new ReadCriteria([$uuid]), $versionContext);
        static::assertCount(1, $basic);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($uuid),
            'version' => Uuid::fromHexToBytes($versionId),
        ]);
        static::assertEmpty($row);
    }

    public function testSearcherConsidersVersionFallback(): void
    {
        $uuid = Uuid::uuid4()->getHex();
        $liveVersionContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $taxData = ['id' => $uuid, 'name' => 'foo tax', 'taxRate' => 5];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $versionId = $this->taxRepository->createVersion($uuid, $liveVersionContext, 'testMerge version');

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($uuid),
            'version' => Uuid::fromHexToBytes($versionId),
        ]);
        static::assertNotEmpty($tax);
        static::assertEquals(5, $tax['tax_rate']);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);
        $this->taxRepository->update([['id' => $uuid, 'name' => 'new merged name', 'taxRate' => 4]], $versionContext);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($uuid),
            'version' => Uuid::fromHexToBytes($versionId),
        ]);
        static::assertNotEmpty($tax);
        static::assertEquals(4, $tax['tax_rate']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('tax.taxRate', 4));

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
        $uuid = Uuid::uuid4()->getHex();

        $methodData = [
            'id' => $uuid,
            'bindShippingfree' => false,
            'name' => 'foo',
            'type' => 1,
            'prices' => [
                [
                    'id' => $uuid,
                    'quantityFrom' => 10,
                    'price' => 10.0,
                    'factor' => 1.0,
                ],
            ],
        ];

        $liveVersionContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->shippingMethodRepository->create([$methodData], $liveVersionContext);

        $changes = $this->getVersionData(ShippingMethodDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $changes = $this->getVersionData(ShippingMethodPriceDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $versionId = $this->shippingMethodRepository->createVersion($uuid, $liveVersionContext);

        $changes = $this->getVersionData(ShippingMethodDefinition::getEntityName(), $uuid, $versionId);
        static::assertCount(1, $changes);

        $changes = $this->getVersionData(ShippingMethodPriceDefinition::getEntityName(), $uuid, $versionId);
        static::assertCount(1, $changes);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);

        $this->shippingMethodRepository->upsert([
            [
                'id' => $uuid,
                'type' => 2,
                'prices' => [
                    ['id' => $uuid, 'price' => 15.0],
                ],
            ],
        ], $versionContext);

        $changes = $this->getVersionData(ShippingMethodDefinition::getEntityName(), $uuid, $versionId);
        static::assertCount(2, $changes);

        $changes = $this->getVersionData(ShippingMethodPriceDefinition::getEntityName(), $uuid, $versionId);
        static::assertCount(2, $changes);

        $criteria = new ReadCriteria([$uuid]);
        $criteria->addAssociation('prices');

        $liveShippingMethod = $this->shippingMethodRepository->read($criteria, $liveVersionContext);
        static::assertCount(1, $liveShippingMethod);
        static::assertTrue($liveShippingMethod->has($uuid));
        $shippingMethod = $liveShippingMethod->get($uuid);

        /* @var ShippingMethodStruct $shippingMethod */
        static::assertEquals(1, $shippingMethod->getType());
        static::assertCount(1, $shippingMethod->getPrices());
        static::assertEquals(10.0, $shippingMethod->getPrices()->get($uuid)->getPrice());

        $criteria = new ReadCriteria([$uuid]);
        $criteria->addAssociation('prices');
        $versionShippingMethod = $this->shippingMethodRepository->read($criteria, $versionContext);
        static::assertCount(1, $versionShippingMethod);
        static::assertTrue($versionShippingMethod->has($uuid));
        $shippingMethod = $versionShippingMethod->get($uuid);

        /* @var ShippingMethodStruct $shippingMethod */
        static::assertEquals(2, $shippingMethod->getType());
        static::assertCount(1, $shippingMethod->getPrices());
        static::assertEquals(15.0, $shippingMethod->getPrices()->get($uuid)->getPrice());

        $this->shippingMethodRepository->merge($versionId, $liveVersionContext);

        $liveShippingMethod = $this->shippingMethodRepository->read($criteria, $liveVersionContext);
        static::assertCount(1, $liveShippingMethod);
        static::assertTrue($liveShippingMethod->has($uuid));
        $shippingMethod = $liveShippingMethod->get($uuid);

        /* @var ShippingMethodStruct $shippingMethod */
        static::assertEquals(2, $shippingMethod->getType());
        static::assertCount(1, $shippingMethod->getPrices());
        static::assertEquals(15.0, $shippingMethod->getPrices()->get($uuid)->getPrice());

        $liveShippingMethod = $this->shippingMethodRepository->read($criteria, $versionContext);
        static::assertCount(1, $liveShippingMethod);
        static::assertTrue($liveShippingMethod->has($uuid));
        $shippingMethod = $liveShippingMethod->get($uuid);

        /* @var ShippingMethodStruct $shippingMethod */
        static::assertEquals(2, $shippingMethod->getType());
        static::assertCount(1, $shippingMethod->getPrices());
        static::assertEquals(15.0, $shippingMethod->getPrices()->get($uuid)->getPrice());
    }

    public function testVersioningWithProductInheritance(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $variantId = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $productId,
                'name' => 'parent',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
            ],
            [
                'id' => $variantId,
                'price' => ['gross' => 15, 'net' => 14, 'linked' => false],
                'parentId' => $productId,
            ],
        ];
        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->productRepository->create($products, $liveContext);

        $variantVersionId = $this->productRepository->createVersion($variantId, $liveContext);
        $versionContext = $liveContext->createWithVersionId($variantVersionId);

        $this->productRepository->update([
            ['id' => $variantId, 'price' => ['gross' => 20, 'net' => 19, 'linked' => false]],
        ], $versionContext);

        $variant = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($variantId),
            'version' => Uuid::fromHexToBytes($variantVersionId),
        ]);

        static::assertEquals(['gross' => 20, 'net' => 19, 'linked' => false], json_decode($variant['price'], true));

        $variants = $this->productRepository->read(new ReadCriteria([$variantId]), $versionContext);
        static::assertCount(1, $variants);
        static::assertTrue($variants->has($variantId));

        $variant = $variants->get($variantId);
        static::assertEquals(new PriceStruct(19, 20, false), $variant->getPrice());
        static::assertEquals('parent', $variant->getName());

        $this->productRepository->createVersion($productId, $liveContext, 'test parent', $variantVersionId);

        $this->productRepository->update([
            ['id' => $productId, 'name' => 'parent version', 'price' => ['gross' => 25, 'net' => 24]],
        ], $versionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, $variantVersionId);
        static::assertCount(2, $changes);

        $changes = $this->getTranslationVersionData(ProductTranslationDefinition::getEntityName(), Defaults::LANGUAGE_EN, 'productId', $productId, $variantVersionId);
        static::assertCount(2, $changes);

        $product = $this->connection->fetchAssoc(
            'SELECT * FROM product_translation WHERE product_id = :id AND product_version_id = :version AND language_id = :language',
            [
                'id' => Uuid::fromHexToBytes($productId),
                'version' => Uuid::fromHexToBytes($variantVersionId),
                'language' => Uuid::fromHexToBytes($versionContext->getLanguageId()),
            ]
        );
        static::assertEquals('parent version', $product['name']);

        $variants = $this->productRepository->read(new ReadCriteria([$productId]), $versionContext);
        static::assertCount(1, $variants);
        static::assertTrue($variants->has($productId));

        $variant = $variants->get($productId);
        static::assertEquals(25, $variant->getPrice()->getGross());
        static::assertEquals('parent version', $variant->getName());

        $variants = $this->productRepository->read(new ReadCriteria([$variantId]), $versionContext);
        static::assertCount(1, $variants);
        static::assertTrue($variants->has($variantId));

        $variant = $variants->get($variantId);
        static::assertEquals(20, $variant->getPrice()->getGross());
        static::assertEquals('parent version', $variant->getName());
    }

    public function testTaxRestrictions(): void
    {
        $id = Uuid::uuid4()->getHex();

        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);

        $this->taxRepository->create([['id' => $id, 'name' => 'test', 'taxRate' => 15]], $liveContext);

        $this->productRepository->create([
            [
                'id' => $id,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'taxId' => $id,
                'manufacturer' => ['name' => 'test'],
            ],
        ], $liveContext);

        $versionId = $this->taxRepository->createVersion($id, $liveContext);

        $versionContext = $liveContext->createWithVersionId($versionId);

        $this->taxRepository->update([
            ['id' => $id, 'taxRate' => 19],
        ], $versionContext);

        $this->taxRepository->merge($versionId, $liveContext);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($id),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        static::assertEquals(19, $tax['tax_rate']);
    }

    public function testMergeBoolField(): void
    {
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
            'id' => Uuid::fromHexToBytes($categoryId),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        static::assertEquals(0, $category['active']);

        $fetchedCategory = $this->categoryRepository->read(new ReadCriteria([$categoryId]), $liveContext)->get($categoryId);
        static::assertEquals(false, $fetchedCategory->get('active'));
    }

    public function testMergeDateTimeField(): void
    {
        static::markTestIncomplete('Should work with NEXT-829');
        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);

        $customerId = Uuid::uuid4();
        $versionId = Uuid::uuid4();

        $address = [
            'firstName' => 'not',
            'lastName' => 'nope',
            'city' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'salutation' => 'not',
            'countryId' => Defaults::COUNTRY,
        ];

        $customer = [
            'id' => $customerId,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultShippingAddress' => $address,
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::uuid4() . '@example.com',
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

        $update = [
            'id' => $customerId,
            'lastLogin' => $updateTime->format(Defaults::DATE_FORMAT),
        ];

        $this->customerRepository->update([$update], $versionContext);

        $this->customerRepository->merge($versionId, $liveContext);

        $customer = $this->customerRepository->read(new ReadCriteria([$customerId]), $liveContext)
            ->get($customerId);

        /** @var CustomerStruct $customer */
        static::assertEquals($updateTime->format(Defaults::DATE_FORMAT), $customer->getLastLogin()->format(Defaults::DATE_FORMAT));
    }

    public function testMergeCalculatedField(): void
    {
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
            'categories' => [
                ['id' => $categories[0]['id']],
                ['id' => $categories[1]['id']],
            ],
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
            'categories' => [
                ['id' => $categories[2]['id']],
                ['id' => $categories[3]['id']],
            ],
        ];

        // In the new version of the product, added two new categories
        $this->productRepository->update([$update], $versionContext);

        $categoryIds = $this->connection->fetchAll(
            'SELECT category_id FROM product_category WHERE product_id = :product AND product_version_id = :version',
            [
                'product' => Uuid::fromHexToBytes($productId),
                'version' => Uuid::fromHexToBytes($versionContext->getVersionId()),
            ]
        );

        $categoryIds = array_map(function ($id) {
            return Uuid::fromBytesToHex($id['category_id']);
        }, $categoryIds);

        static::assertCount(4, $categoryIds);

        static::assertContains($categories[0]['id'], $categoryIds);
        static::assertContains($categories[1]['id'], $categoryIds);
        static::assertContains($categories[2]['id'], $categoryIds);
        static::assertContains($categories[3]['id'], $categoryIds);

        $this->productRepository->merge($versionId, $liveContext);

        $categoryIds = $this->connection->fetchAll(
            'SELECT category_id FROM product_category WHERE product_id = :product AND product_version_id = :version',
            [
                'product' => Uuid::fromHexToBytes($productId),
                'version' => Uuid::fromHexToBytes($liveContext->getVersionId()),
            ]
        );

        $categoryIds = array_map(function ($id) {
            return Uuid::fromBytesToHex($id['category_id']);
        }, $categoryIds);

        static::assertCount(4, $categoryIds);

        static::assertContains($categories[0]['id'], $categoryIds);
        static::assertContains($categories[1]['id'], $categoryIds);
        static::assertContains($categories[2]['id'], $categoryIds);
        static::assertContains($categories[3]['id'], $categoryIds);

        $fetchedProductUpdated = $this->productRepository->read(new ReadCriteria([$productId]), $liveContext)->get($productId);

        $updatedCategories = $fetchedProductUpdated->getCategoryTree();

        // This fails because of NEXT-670.
        static::assertEquals(4, \count($updatedCategories));

        static::assertContains($categories[2]['id'], $updatedCategories);
        static::assertContains($categories[3]['id'], $updatedCategories);
    }

    public function testMergeMediaItems(): void
    {
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
        static::assertEquals($metadata->getRawMetadata(), $fetchedUpdatedMedia->getMetaData()->getRawMetadata());
    }

    public function testCampaign(): void
    {
        $liveContext = Context::createDefaultContext(Defaults::TENANT_ID);

        $parentCategoryId = $this->createCategory($liveContext);

        $product1 = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();

        $category = Uuid::uuid4()->getHex();
        $versionId = Uuid::uuid4()->getHex();

        $taxId1 = Uuid::uuid4()->getHex();
        $taxId2 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $product1,
                'name' => 'product test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $taxId1, 'name' => 'test', 'taxRate' => 7],
                'categories' => [
                    ['id' => $category, 'parentId' => $parentCategoryId, 'name' => 'TEST cat'],
                ],
            ], [
                'id' => $product2,
                'name' => 'product test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => $taxId2, 'name' => 'test', 'taxRate' => 7],
                'categories' => [
                    ['id' => $category],
                ],
            ],
        ];

        $this->productRepository->create($products, $liveContext);

        $this->productRepository->createVersion($product1, $liveContext, 'Campaign', $versionId);

        $versionContext = $liveContext->createWithVersionId($versionId);
        $update = ['id' => $product1, 'tax' => ['id' => $taxId1, 'name' => 'test', 'taxRate' => 19]];
        $this->productRepository->update([$update], $versionContext);

        $versionId = $this->productRepository->createVersion($product2, $liveContext, 'Campaign', $versionId);

        $versionContext = $liveContext->createWithVersionId($versionId);
        $update = ['id' => $product2, 'tax' => ['id' => $taxId2, 'name' => 'test', 'taxRate' => 25]];
        $this->productRepository->update([$update], $versionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1, $versionId);
        static::assertCount(2, $changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2, $versionId);
        static::assertCount(2, $changes);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.id', $category));
        $criteria->addFilter(new RangeFilter('product.tax.taxRate', [RangeFilter::GTE => 19]));

        $search = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1, $search->getIds());
        static::assertContains($product2, $search->getIds());

        $notExisting = Uuid::uuid4()->getHex();
        $notExistingContext = $versionContext->createWithVersionId($notExisting);

        $search = $this->productRepository->searchIds($criteria, $notExistingContext);
        static::assertCount(0, $search->getIds());

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        static::assertCount(0, $search->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.id', $category));
        $criteria->addFilter(new EqualsFilter('product.tax.taxRate', 7));

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1, $search->getIds());
        static::assertContains($product2, $search->getIds());

        $search = $this->productRepository->searchIds($criteria, $notExistingContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1, $search->getIds());
        static::assertContains($product2, $search->getIds());

        //MERGE
        $this->productRepository->merge($versionId, $liveContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1, $versionId);
        static::assertEmpty($changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2, $versionId);
        static::assertEmpty($changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1, Defaults::LIVE_VERSION);
        static::assertCount(2, $changes);
        static::assertEquals('insert', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2, Defaults::LIVE_VERSION);
        static::assertCount(2, $changes);
        static::assertEquals('insert', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2, $versionId);
        static::assertEmpty($changes);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :version',
            ['id' => Uuid::fromHexToBytes($taxId1), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
        );
        static::assertArraySubset(['name' => 'test', 'tax_rate' => 19], $tax);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :version',
            ['id' => Uuid::fromHexToBytes($taxId2), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
        );
        static::assertArraySubset(['name' => 'test', 'tax_rate' => 25], $tax);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.id', $category));
        $criteria->addFilter(new RangeFilter('product.tax.taxRate', [RangeFilter::GTE => 19]));

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1, $search->getIds());
        static::assertContains($product2, $search->getIds());
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
                'version' => Uuid::fromHexToBytes($versionId),
                'tenant' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
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
                'tenant' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            ]
        );
    }

    private function createCategory(Context $context, array $override = []): string
    {
        $id = Uuid::uuid4()->getHex();
        $payload = array_merge(
            [
                'id' => $id,
                'name' => 'Random category name',
                'catalogId' => $context->getCatalogIds()[0],
            ],
            $override
        );

        $this->getContainer()->get('category.repository')->create([$payload], $context);

        return $payload['id'];
    }
}
