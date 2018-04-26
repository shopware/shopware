<?php declare(strict_types=1);

namespace Shopware\Api\Test\Version;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\RangeQuery;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Definition\ProductTranslationDefinition;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Product\Struct\PriceStruct;
use Shopware\Api\Tax\Definition\TaxAreaRuleDefinition;
use Shopware\Api\Tax\Definition\TaxAreaRuleTranslationDefinition;
use Shopware\Api\Tax\Definition\TaxDefinition;
use Shopware\Api\Tax\Repository\TaxRepository;
use Shopware\Api\Tax\Struct\TaxDetailStruct;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class VersioningTest extends KernelTestCase
{
    /**
     * @var TaxRepository
     */
    private $taxRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    public function setUp()
    {
        $kernel = static::bootKernel();

        $this->taxRepository = $kernel->getContainer()->get(TaxRepository::class);
        $this->productRepository = $kernel->getContainer()->get(ProductRepository::class);
        $this->connection = $kernel->getContainer()->get(Connection::class);
        $this->connection->beginTransaction();
        $this->connection->executeUpdate('DELETE FROM product');
        $this->connection->executeUpdate('DELETE FROM tax');
        $this->connection->executeUpdate('DELETE FROM version_commit');
        $this->connection->executeUpdate('DELETE FROM version_commit_data');
    }

    public function tearDown()
    {
        $this->connection->rollBack();
    }

    public function testVersionChangeOnInsert(): void
    {
        $uuid = Uuid::uuid4()->getHex();
        $context = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $taxData = [
            'id' => $uuid,
            'name' => 'foo tax',
            'rate' => 20,
            'tenantId' => Defaults::TENANT_ID,
        ];

        $this->taxRepository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes, sprintf('Change for entity_id "%s" was not created.', $uuid));

        $change = array_shift($changes);

        $taxData['versionId'] = Defaults::LIVE_VERSION;

        $payload = json_decode($change['payload'], true);
        unset($payload['createdAt']);
        $this->assertEquals($taxData, $payload);
    }

    public function testVersionChangeOnInsertWithSubresources(): void
    {
        $uuid = Uuid::uuid4()->getHex();
        $ruleId = Uuid::uuid4()->getHex();

        $context = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $taxData = [
            'id' => $uuid,
            'name' => 'foo tax',
            'rate' => 20,
            'areaRules' => [
                [
                    'id' => $ruleId,
                    'taxRate' => 99,
                    'active' => true,
                    'name' => 'required',
                    'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                ],
            ],
        ];

        $this->taxRepository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);
        $taxChange = [
            'id' => $uuid,
            'versionId' => Defaults::LIVE_VERSION,
            'name' => 'foo tax',
            'rate' => 20,
            'tenantId' => Defaults::TENANT_ID,
        ];
        $payload = json_decode($changes[0]['payload'], true);
        unset($payload['createdAt']);
        $this->assertEquals($taxChange, $payload);

        $changes = $this->getVersionData(TaxAreaRuleDefinition::getEntityName(), $ruleId, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);
        $taxAreaChange = [
            'id' => $ruleId,
            'versionId' => Defaults::LIVE_VERSION,
            'taxId' => $uuid,
            'taxRate' => 99,
            'active' => 1,
            'customerGroupId' => '3294e6f6372b415fac7371cbc191548f',
            'taxVersionId' => 'ffffffffffffffffffffffffffffffff',
            'customerGroupVersionId' => 'ffffffffffffffffffffffffffffffff',
            'countryVersionId' => 'ffffffffffffffffffffffffffffffff',
            'countryAreaVersionId' => 'ffffffffffffffffffffffffffffffff',
            'countryStateVersionId' => 'ffffffffffffffffffffffffffffffff',
            'tenantId' => 'ffffffffffffffffffffffffffffffff',
        ];
        $payload = json_decode($changes[0]['payload'], true);
        unset($payload['createdAt']);
        $this->assertEquals($taxAreaChange, $payload);

        $changes = $this->getTranslationVersionData(TaxAreaRuleTranslationDefinition::getEntityName(), Defaults::LANGUAGE, 'taxAreaRuleId', $ruleId, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);
        $taxAreaTranslationChange = [
            'taxAreaRuleId' => $ruleId,
            'name' => 'required',
            'languageId' => Defaults::LANGUAGE,
            'taxAreaRuleVersionId' => Defaults::LIVE_VERSION,
        ];
        $payload = json_decode($changes[0]['payload'], true);
        unset($payload['createdAt']);

        $this->assertEquals($taxAreaTranslationChange, $payload);
    }

    public function testCreateNewVersion(): void
    {
        $uuid = Uuid::uuid4();
        $context = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $taxData = [
            'id' => $uuid->getHex(),
            'name' => 'foo tax',
            'rate' => 20,
        ];

        $this->taxRepository->create([$taxData], $context);

        $versionId = $this->taxRepository->createVersion($uuid->getHex(), $context, 'testCreateVersionWithoutRelations version');

        $this->assertNotEmpty($versionId);

        $versionId = Uuid::fromString($versionId);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :versionId',
            [
                'id' => $uuid->getBytes(),
                'versionId' => $versionId->getBytes(),
            ]
        );

        $this->assertNotFalse($tax, 'Tax clone was not created.');

        $this->assertEquals($uuid->getBytes(), $tax['id']);
        $this->assertEquals($versionId->getBytes(), $tax['version_id']);
        $this->assertEquals('foo tax', $tax['name']);
        $this->assertEquals(20, $tax['tax_rate']);
    }

    public function testCreateNewVersionWithSubresources(): void
    {
        $uuid = Uuid::uuid4();
        $ruleId = Uuid::uuid4();
        $context = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);

        $taxData = [
            'id' => $uuid->getHex(),
            'name' => 'foo tax',
            'rate' => 20,
            'areaRules' => [
                [
                    'id' => $ruleId->getHex(),
                    'taxRate' => 99,
                    'active' => true,
                    'name' => 'required',
                    'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                ],
            ],
        ];

        $this->taxRepository->create([$taxData], $context);

        $versionId = $this->taxRepository->createVersion($uuid->getHex(), $context, 'testCreateVersionWithSubresources version');

        $this->assertNotEmpty($versionId);

        $versionId = Uuid::fromString($versionId);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :versionId',
            [
                'id' => $uuid->getBytes(),
                'versionId' => $versionId->getBytes(),
            ]
        );

        $taxRules = $this->connection->fetchAll(
            'SELECT * FROM tax_area_rule WHERE tax_id = :id AND version_id = :versionId',
            [
                'id' => $uuid->getBytes(),
                'versionId' => $versionId->getBytes(),
            ]
        );

        $this->assertNotFalse($tax, 'Tax clone was not created.');
        $this->assertCount(1, $taxRules, 'Tax area rule clones were not created.');

        $this->assertEquals($uuid->getBytes(), $tax['id']);
        $this->assertEquals($versionId->getBytes(), $tax['version_id']);
        $this->assertEquals('foo tax', $tax['name']);
        $this->assertEquals(20, $tax['tax_rate']);

        $this->assertEquals($uuid->getBytes(), $taxRules[0]['tax_id']);
        $this->assertEquals($versionId->getBytes(), $taxRules[0]['version_id']);
    }

    public function testMergeVersions(): void
    {
        $uuid = Uuid::uuid4();
        $context = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $taxData = ['id' => $uuid->getHex(), 'name' => 'foo tax', 'rate' => 20];
        $this->taxRepository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);

        $versionId = $this->taxRepository->createVersion($uuid->getHex(), $context, 'testMerge version');
        $versionId = Uuid::fromString($versionId);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId->getHex());
        $this->assertCount(1, $changes);
        $this->assertEquals('clone', $changes[0]['action']);

        $versionContext = $context->createWithVersionId($versionId->getHex());
        $this->taxRepository->update([['id' => $uuid->getHex(), 'name' => 'new merged name']], $versionContext);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
        ]);
        $this->assertEquals('foo tax', $row['name']);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId->getHex());
        $this->assertCount(2, $changes);
        $this->assertEquals('clone', $changes[0]['action']);
        $this->assertEquals('update', $changes[1]['action']);

        $this->taxRepository->merge($versionId->getHex(), $context);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
        ]);

        $this->assertEquals('new merged name', $row['name']);

        $row = $this->connection->fetchAssoc('SELECT * FROM version WHERE id = :id', ['id' => $versionId->getBytes()]);
        $this->assertEmpty($row);

        $row = $this->connection->fetchAssoc('SELECT * FROM version_commit WHERE version_id = :id', ['id' => $versionId->getBytes()]);
        $this->assertEmpty($row);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId->getHex());
        $this->assertEmpty($changes);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), Defaults::LIVE_VERSION);
        $this->assertCount(2, $changes);

        $this->assertEquals('insert', $changes[0]['action']);
        $this->assertEquals('update', $changes[1]['action']);
    }

    public function testReadConsiderVersion(): void
    {
        $uuid = Uuid::uuid4();
        $liveVersionContext = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $taxData = ['id' => $uuid->getHex(), 'name' => 'foo tax', 'rate' => 20];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);

        $versionId = $this->taxRepository->createVersion($uuid->getHex(), $liveVersionContext, 'testMerge version');
        $versionId = Uuid::fromString($versionId);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId->getHex());
        $this->assertCount(1, $changes);
        $this->assertEquals('clone', $changes[0]['action']);

        $versionContext = $liveVersionContext->createWithVersionId($versionId->getHex());
        $this->taxRepository->update([['id' => $uuid->getHex(), 'name' => 'new merged name']], $versionContext);

        $basic = $this->taxRepository->readBasic([$uuid->getHex()], $liveVersionContext);
        $this->assertCount(1, $basic);
        $this->assertTrue($basic->has($uuid->getHex()));
        $tax = $basic->get($uuid->getHex());
        $this->assertEquals('foo tax', $tax->getName());

        $basic = $this->taxRepository->readBasic([$uuid->getHex()], $versionContext);
        $this->assertCount(1, $basic);
        $this->assertTrue($basic->has($uuid->getHex()));
        $tax = $basic->get($uuid->getHex());
        $this->assertEquals('new merged name', $tax->getName());

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
        ]);
        $this->assertEquals('foo tax', $row['name']);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId->getHex());
        $this->assertCount(2, $changes);
        $this->assertEquals('clone', $changes[0]['action']);
        $this->assertEquals('update', $changes[1]['action']);

        $this->taxRepository->merge($versionId->getHex(), $liveVersionContext);

        $basic = $this->taxRepository->readBasic([$uuid->getHex()], $liveVersionContext);
        $this->assertCount(1, $basic);
        $this->assertTrue($basic->has($uuid->getHex()));
        $tax = $basic->get($uuid->getHex());
        $this->assertEquals('new merged name', $tax->getName());

        $basic = $this->taxRepository->readBasic([$uuid->getHex()], $versionContext);
        $this->assertCount(1, $basic);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString($versionId)->getBytes(),
        ]);
        $this->assertEmpty($row);
    }

    public function testSearcherConsidersVersionFallback(): void
    {
        $uuid = Uuid::uuid4();
        $liveVersionContext = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $taxData = ['id' => $uuid->getHex(), 'name' => 'foo tax', 'rate' => 5];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $versionId = $this->taxRepository->createVersion($uuid->getHex(), $liveVersionContext, 'testMerge version');
        $versionId = Uuid::fromString($versionId);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => $versionId->getBytes(),
        ]);
        $this->assertNotEmpty($tax);
        $this->assertEquals(5, $tax['tax_rate']);

        $versionContext = $liveVersionContext->createWithVersionId($versionId->getHex());
        $this->taxRepository->update([['id' => $uuid->getHex(), 'name' => 'new merged name', 'rate' => 4]], $versionContext);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => $versionId->getBytes(),
        ]);
        $this->assertNotEmpty($tax);
        $this->assertEquals(4, $tax['tax_rate']);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('tax.rate', 4));

        $result = $this->taxRepository->searchIds($criteria, $liveVersionContext);
        $this->assertEquals(0, $result->getTotal());

        $result = $this->taxRepository->searchIds($criteria, $versionContext);
        $this->assertEquals(1, $result->getTotal());

        $taxData = ['name' => 'foo tax', 'rate' => 4];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $result = $this->taxRepository->searchIds($criteria, $versionContext);
        $this->assertEquals(2, $result->getTotal());

        $result = $this->taxRepository->searchIds($criteria, $liveVersionContext);
        $this->assertEquals(1, $result->getTotal());
    }

    public function testOneToManyVersioning(): void
    {
        $uuid = Uuid::uuid4();
        $liveVersionContext = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $taxData = [
            'id' => $uuid->getHex(),
            'name' => 'foo tax',
            'rate' => 5,
            'areaRules' => [
                [
                    'id' => $uuid->getHex(),
                    'taxRate' => 6,
                    'name' => 'test',
                    'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                ],
            ],
        ];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);

        $changes = $this->getVersionData(TaxAreaRuleDefinition::getEntityName(), $uuid->getHex(), Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);

        $versionId = $this->taxRepository->createVersion($uuid->getHex(), $liveVersionContext);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId);
        $this->assertCount(1, $changes);

        $changes = $this->getVersionData(TaxAreaRuleDefinition::getEntityName(), $uuid->getHex(), $versionId);
        $this->assertCount(1, $changes);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);

        $this->taxRepository->upsert([
            [
                'id' => $uuid->getHex(),
                'rate' => 15,
                'areaRules' => [
                    ['id' => $uuid->getHex(), 'taxRate' => 16],
                ],
            ],
        ], $versionContext);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->getHex(), $versionId);
        $this->assertCount(2, $changes);

        $changes = $this->getVersionData(TaxAreaRuleDefinition::getEntityName(), $uuid->getHex(), $versionId);
        $this->assertCount(2, $changes);

        $liveTax = $this->taxRepository->readDetail([$uuid->getHex()], $liveVersionContext);
        $this->assertCount(1, $liveTax);
        $this->assertTrue($liveTax->has($uuid->getHex()));
        $tax = $liveTax->get($uuid->getHex());

        /* @var TaxDetailStruct $tax */
        $this->assertEquals(5, $tax->getRate());
        $this->assertCount(1, $tax->getAreaRules());
        $this->assertEquals(6, $tax->getAreaRules()->get($uuid->getHex())->getTaxRate());

        $versionTax = $this->taxRepository->readDetail([$uuid->getHex()], $versionContext);
        $this->assertCount(1, $versionTax);
        $this->assertTrue($versionTax->has($uuid->getHex()));
        $tax = $versionTax->get($uuid->getHex());

        /* @var TaxDetailStruct $tax */
        $this->assertEquals(15, $tax->getRate());
        $this->assertCount(1, $tax->getAreaRules());
        $this->assertEquals(16, $tax->getAreaRules()->get($uuid->getHex())->getTaxRate());

        $this->taxRepository->merge($versionId, $liveVersionContext);

        $liveTax = $this->taxRepository->readDetail([$uuid->getHex()], $liveVersionContext);
        $this->assertCount(1, $liveTax);
        $this->assertTrue($liveTax->has($uuid->getHex()));
        $tax = $liveTax->get($uuid->getHex());

        /* @var TaxDetailStruct $tax */
        $this->assertEquals(15, $tax->getRate());
        $this->assertCount(1, $tax->getAreaRules());
        $this->assertEquals(16, $tax->getAreaRules()->get($uuid->getHex())->getTaxRate());

        $liveTax = $this->taxRepository->readDetail([$uuid->getHex()], $versionContext);
        $this->assertCount(1, $liveTax);
        $this->assertTrue($liveTax->has($uuid->getHex()));
        $tax = $liveTax->get($uuid->getHex());

        /* @var TaxDetailStruct $tax */
        $this->assertEquals(15, $tax->getRate());
        $this->assertCount(1, $tax->getAreaRules());
        $this->assertEquals(16, $tax->getAreaRules()->get($uuid->getHex())->getTaxRate());
    }

    public function testVersioningWithProductInheritance(): void
    {
        $productId = Uuid::uuid4();
        $variantId = Uuid::uuid4();

        $products = [
            [
                'id' => $productId->getHex(),
                'name' => 'parent',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['rate' => 18, 'name' => 'test'],
            ],
            [
                'id' => $variantId->getHex(),
                'price' => ['gross' => 15, 'net' => 14],
                'parentId' => $productId->getHex(),
            ],
        ];
        $liveContext = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $this->productRepository->create($products, $liveContext);

        $variantVersionId = $this->productRepository->createVersion($variantId->getHex(), $liveContext);
        $versionContext = $liveContext->createWithVersionId($variantVersionId);

        $this->productRepository->update([
            ['id' => $variantId->getHex(), 'price' => ['gross' => 20, 'net' => 19]],
        ], $versionContext);

        $variant = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => $variantId->getBytes(),
            'version' => Uuid::fromString($variantVersionId)->getBytes(),
        ]);

        $this->assertEquals(['gross' => 20, 'net' => 19], json_decode($variant['price'], true));

        $variants = $this->productRepository->readBasic([$variantId->getHex()], $versionContext);
        $this->assertCount(1, $variants);
        $this->assertTrue($variants->has($variantId->getHex()));

        $variant = $variants->get($variantId->getHex());
        $this->assertEquals(new PriceStruct(19, 20), $variant->getPrice());
        $this->assertEquals('parent', $variant->getName());

        $this->productRepository->createVersion($productId->getHex(), $liveContext, 'test parent', $variantVersionId);

        $this->productRepository->update([
            ['id' => $productId->getHex(), 'name' => 'parent version', 'price' => ['gross' => 25, 'net' => 24]],
        ], $versionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId->getHex(), $variantVersionId);
        $this->assertCount(2, $changes);

        $changes = $this->getTranslationVersionData(ProductTranslationDefinition::getEntityName(), Defaults::LANGUAGE, 'productId', $productId->getHex(), $variantVersionId);
        $this->assertCount(2, $changes);

        $product = $this->connection->fetchAssoc(
            'SELECT * FROM product_translation WHERE product_id = :id AND product_version_id = :version AND language_id = :language',
            [
                'id' => $productId->getBytes(),
                'version' => Uuid::fromString($variantVersionId)->getBytes(),
                'language' => Uuid::fromString($versionContext->getLanguageId())->getBytes(),
            ]
        );
        $this->assertEquals('parent version', $product['name']);

        $variants = $this->productRepository->readBasic([$productId->getHex()], $versionContext);
        $this->assertCount(1, $variants);
        $this->assertTrue($variants->has($productId->getHex()));

        $variant = $variants->get($productId->getHex());
        $this->assertEquals(25, $variant->getPrice()->getGross());
        $this->assertEquals('parent version', $variant->getName());

        $variants = $this->productRepository->readBasic([$variantId->getHex()], $versionContext);
        $this->assertCount(1, $variants);
        $this->assertTrue($variants->has($variantId->getHex()));

        $variant = $variants->get($variantId->getHex());
        $this->assertEquals(20, $variant->getPrice()->getGross());
        $this->assertEquals('parent version', $variant->getName());
    }

    public function testTaxRestrictions(): void
    {
        $id = Uuid::uuid4();

        $liveContext = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);

        $this->taxRepository->create([['id' => $id->getHex(), 'name' => 'test', 'rate' => 15]], $liveContext);

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
            ['id' => $id->getHex(), 'rate' => 19],
        ], $versionContext);

        $this->taxRepository->merge($versionId, $liveContext);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $id->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
        ]);

        $this->assertEquals(19, $tax['tax_rate']);
    }

    public function testCampaign(): void
    {
        $product1 = Uuid::uuid4();
        $product2 = Uuid::uuid4();

        $parentCategory = $this->connection->fetchColumn(
            'SELECT id FROM category WHERE parent_id = :main',
            ['main' => Uuid::fromString(Defaults::ROOT_CATEGORY)->getBytes()]
        );
        $parentCategory = Uuid::fromBytes($parentCategory);

        $category = Uuid::uuid4()->getHex();
        $versionId = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $product1->getHex(),
                'name' => 'product test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 19],
                'categories' => [
                    ['id' => $category, 'parentId' => $parentCategory->getHex(), 'name' => 'TEST cat'],
                ],
            ], [
                'id' => $product2->getHex(),
                'name' => 'product test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 19],
                'categories' => [
                    ['id' => $category],
                ],
            ],
        ];

        $liveContext = ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID);
        $this->productRepository->create($products, $liveContext);

        $this->productRepository->createVersion($product1->getHex(), $liveContext, 'Campaign', $versionId);

        $versionContext = $liveContext->createWithVersionId($versionId);
        $update = ['id' => $product1->getHex(), 'price' => ['gross' => 100, 'net' => 9]];
        $this->productRepository->update([$update], $versionContext);

        $versionId = $this->productRepository->createVersion($product2->getHex(), $liveContext, 'Campaign', $versionId);

        $versionContext = $liveContext->createWithVersionId($versionId);
        $update = ['id' => $product2->getHex(), 'price' => ['gross' => 200, 'net' => 9]];
        $this->productRepository->update([$update], $versionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1->getHex(), $versionId);
        $this->assertCount(2, $changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->getHex(), $versionId);
        $this->assertCount(2, $changes);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.categories.id', $category));
        $criteria->addFilter(new RangeQuery('product.price', [RangeQuery::GTE => 100]));

        $search = $this->productRepository->searchIds($criteria, $versionContext);
        $this->assertCount(2, $search->getIds());
        $this->assertContains($product1->getHex(), $search->getIds());
        $this->assertContains($product2->getHex(), $search->getIds());

        $notExisting = Uuid::uuid4()->getHex();
        $notExistingContext = $versionContext->createWithVersionId($notExisting);

        $search = $this->productRepository->searchIds($criteria, $notExistingContext);
        $this->assertCount(0, $search->getIds());

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        $this->assertCount(0, $search->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.categories.id', $category));
        $criteria->addFilter(new TermQuery('product.price', 10));

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        $this->assertCount(2, $search->getIds());
        $this->assertContains($product1->getHex(), $search->getIds());
        $this->assertContains($product2->getHex(), $search->getIds());

        $search = $this->productRepository->searchIds($criteria, $notExistingContext);
        $this->assertCount(2, $search->getIds());
        $this->assertContains($product1->getHex(), $search->getIds());
        $this->assertContains($product2->getHex(), $search->getIds());

        //MERGE
        $this->productRepository->merge($versionId, $liveContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1->getHex(), $versionId);
        $this->assertEmpty($changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->getHex(), $versionId);
        $this->assertEmpty($changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1->getHex(), Defaults::LIVE_VERSION);
        $this->assertCount(2, $changes);
        $this->assertEquals('insert', $changes[0]['action']);
        $this->assertEquals('update', $changes[1]['action']);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->getHex(), Defaults::LIVE_VERSION);
        $this->assertCount(2, $changes);
        $this->assertEquals('insert', $changes[0]['action']);
        $this->assertEquals('update', $changes[1]['action']);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->getHex(), $versionId);
        $this->assertEmpty($changes);

        $product = $this->connection->fetchAssoc(
            'SELECT * FROM product WHERE id = :id AND version_id = :version',
            ['id' => $product1->getBytes(), 'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()]
        );
        $this->assertEquals(['gross' => 100, 'net' => 9], json_decode($product['price'], true));

        $product = $this->connection->fetchAssoc(
            'SELECT * FROM product WHERE id = :id AND version_id = :version',
            ['id' => $product2->getBytes(), 'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()]
        );
        $this->assertEquals(['gross' => 200, 'net' => 9], json_decode($product['price'], true));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.categories.id', $category));
        $criteria->addFilter(new RangeQuery('product.price', [RangeQuery::GTE => 100]));

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        $this->assertCount(2, $search->getIds());
        $this->assertContains($product1->getHex(), $search->getIds());
        $this->assertContains($product2->getHex(), $search->getIds());
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
             ORDER BY ai",
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
             ORDER BY ai",
            [
                'entity' => $entity,
                'id' => $foreignKey,
                'language' => $languageId,
                'version' => $versionId,
                'tenant' => Uuid::fromString(Defaults::TENANT_ID)->getBytes(),
            ]
        );
    }
}
