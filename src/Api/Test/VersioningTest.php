<?php

namespace Shopware\Api\Test;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\RangeQuery;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Entity\Write\Validation\RestrictDeleteViolationException;
use Shopware\Api\Product\Definition\ProductDefinition;
use Shopware\Api\Product\Definition\ProductTranslationDefinition;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Tax\Definition\TaxAreaRuleDefinition;
use Shopware\Api\Tax\Definition\TaxAreaRuleTranslationDefinition;
use Shopware\Api\Tax\Definition\TaxDefinition;
use Shopware\Api\Tax\Repository\TaxRepository;
use Shopware\Api\Tax\Struct\TaxDetailStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Defaults;
use Shopware\Version\VersionManager;
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

    public function testVersionChangeOnInsert()
    {
        $uuid = Uuid::uuid4()->toString();
        $context = TranslationContext::createDefaultContext();
        $taxData = [
            'id' => $uuid,
            'name' => 'foo tax',
            'rate' => 20,
        ];

        $this->taxRepository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes, sprintf('Change for entity_id "%s" was not created.', $uuid));

        $change = array_shift($changes);

        $taxData['versionId'] = Defaults::LIVE_VERSION;

        $this->assertEquals($taxData, json_decode($change['payload'], true));
    }

    public function testVersionChangeOnInsertWithSubresources()
    {
        $uuid = Uuid::uuid4()->toString();
        $ruleId = Uuid::uuid4()->toString();

        $context = TranslationContext::createDefaultContext();
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
                    'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP
                ],
            ]
        ];

        $this->taxRepository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);
        $taxChange = [
            'id' => $uuid,
            'versionId' => Defaults::LIVE_VERSION,
            'name' => 'foo tax',
            'rate' => 20,
        ];
        $this->assertEquals($taxChange, json_decode($changes[0]['payload'], true));

        $changes = $this->getVersionData(TaxAreaRuleDefinition::getEntityName(), $ruleId, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);
        $taxAreaChange = [
            'id' => $ruleId,
            'versionId' => Defaults::LIVE_VERSION,
            'taxId' => $uuid,
            'taxRate' => 99,
            'active' => 1,
            'customerGroupId' => '3294e6f6-372b-415f-ac73-71cbc191548f',
            'taxVersionId' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'customerGroupVersionId' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'countryVersionId' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'countryAreaVersionId' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            'countryStateVersionId' => 'ffffffff-ffff-ffff-ffff-ffffffffffff'
        ];
        $this->assertEquals($taxAreaChange, json_decode($changes[0]['payload'], true));


        $changes = $this->getTranslationVersionData(TaxAreaRuleTranslationDefinition::getEntityName(), Defaults::SHOP, 'taxAreaRuleId', $ruleId, Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);
        $taxAreaTranslationChange = [
            'taxAreaRuleId' => $ruleId,
            'name' => 'required',
            'languageId' => Defaults::SHOP,
            'versionId' => Defaults::LIVE_VERSION,
            'languageVersionId' => Defaults::LIVE_VERSION
        ];
        $this->assertEquals($taxAreaTranslationChange, json_decode($changes[0]['payload'], true));
    }

    public function testCreateNewVersion()
    {
        $uuid = Uuid::uuid4();
        $context = TranslationContext::createDefaultContext();
        $taxData = [
            'id' => $uuid->toString(),
            'name' => 'foo tax',
            'rate' => 20,
        ];

        $this->taxRepository->create([$taxData], $context);

        $versionId = $this->taxRepository->createVersion($uuid->toString(), $context, 'testCreateVersionWithoutRelations version');

        $this->assertNotEmpty($versionId);

        $versionId = Uuid::fromString($versionId);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :versionId',
            [
                'id' => $uuid->getBytes(),
                'versionId' => $versionId->getBytes()
            ]
        );

        $this->assertNotFalse($tax, 'Tax clone was not created.');

        $this->assertEquals($uuid->getBytes(), $tax['id']);
        $this->assertEquals($versionId->getBytes(), $tax['version_id']);
        $this->assertEquals('foo tax', $tax['name']);
        $this->assertEquals(20, $tax['tax_rate']);
    }

    public function testCreateNewVersionWithSubresources()
    {
        $uuid = Uuid::uuid4();
        $ruleId = Uuid::uuid4();
        $context = TranslationContext::createDefaultContext();

        $taxData = [
            'id' => $uuid->toString(),
            'name' => 'foo tax',
            'rate' => 20,
            'areaRules' => [
                [
                    'id' => $ruleId->toString(),
                    'taxRate' => 99,
                    'active' => true,
                    'name' => 'required',
                    'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP
                ],
            ]
        ];

        $this->taxRepository->create([$taxData], $context);

        $versionId = $this->taxRepository->createVersion($uuid->toString(), $context, 'testCreateVersionWithSubresources version');

        $this->assertNotEmpty($versionId);

        $versionId = Uuid::fromString($versionId);

        $tax = $this->connection->fetchAssoc(
            'SELECT * FROM tax WHERE id = :id AND version_id = :versionId',
            [
                'id' => $uuid->getBytes(),
                'versionId' => $versionId->getBytes()
            ]
        );

        $taxRules = $this->connection->fetchAll(
            'SELECT * FROM tax_area_rule WHERE tax_id = :id AND version_id = :versionId',
            [
                'id' => $uuid->getBytes(),
                'versionId' => $versionId->getBytes()
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

    public function testMergeVersions()
    {
        $uuid = Uuid::uuid4();
        $context = TranslationContext::createDefaultContext();
        $taxData = ['id' => $uuid->toString(), 'name' => 'foo tax', 'rate' => 20];
        $this->taxRepository->create([$taxData], $context);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);

        $versionId = $this->taxRepository->createVersion($uuid->toString(), $context, 'testMerge version');
        $versionId = Uuid::fromString($versionId);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), $versionId);
        $this->assertCount(1, $changes);
        $this->assertEquals('clone', $changes[0]['action']);

        $versionContext = new TranslationContext(
            $context->getShopId(),
            $context->isDefaultShop(),
            $context->getFallbackId(),
            $versionId->toString()
        );
        $this->taxRepository->update([['id' => $uuid->toString(), 'name' => 'new merged name']], $versionContext);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()
        ]);
        $this->assertEquals('foo tax', $row['name']);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), $versionId->toString());
        $this->assertCount(2, $changes);
        $this->assertEquals('clone', $changes[0]['action']);
        $this->assertEquals('update', $changes[1]['action']);

        $this->taxRepository->merge($versionId->toString(), $context);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()
        ]);

        $this->assertEquals('new merged name', $row['name']);

        $row = $this->connection->fetchAssoc('SELECT * FROM version WHERE id = :id', ['id' => $versionId->getBytes()]);
        $this->assertEmpty($row);

        $row = $this->connection->fetchAssoc('SELECT * FROM version_commit WHERE version_id = :id', ['id' => $versionId->getBytes()]);
        $this->assertEmpty($row);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), $versionId->toString());
        $this->assertEmpty($changes);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), Defaults::LIVE_VERSION);
        $this->assertCount(2, $changes);

        $this->assertEquals('insert', $changes[0]['action']);
        $this->assertEquals('update', $changes[1]['action']);
    }

    public function testReadConsiderVersion()
    {
        $uuid = Uuid::uuid4();
        $liveVersionContext = TranslationContext::createDefaultContext();
        $taxData = ['id' => $uuid->toString(), 'name' => 'foo tax', 'rate' => 20];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);

        $versionId = $this->taxRepository->createVersion($uuid->toString(), $liveVersionContext, 'testMerge version');
        $versionId = Uuid::fromString($versionId);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), $versionId);
        $this->assertCount(1, $changes);
        $this->assertEquals('clone', $changes[0]['action']);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);
        $this->taxRepository->update([['id' => $uuid->toString(), 'name' => 'new merged name']], $versionContext);

        $basic = $this->taxRepository->readBasic([$uuid->toString()], $liveVersionContext);
        $this->assertCount(1, $basic);
        $this->assertTrue($basic->has($uuid->toString()));
        $tax = $basic->get($uuid->toString());
        $this->assertEquals('foo tax', $tax->getName());

        $basic = $this->taxRepository->readBasic([$uuid->toString()], $versionContext);
        $this->assertCount(1, $basic);
        $this->assertTrue($basic->has($uuid->toString()));
        $tax = $basic->get($uuid->toString());
        $this->assertEquals('new merged name', $tax->getName());

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()
        ]);
        $this->assertEquals('foo tax', $row['name']);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), $versionId->toString());
        $this->assertCount(2, $changes);
        $this->assertEquals('clone', $changes[0]['action']);
        $this->assertEquals('update', $changes[1]['action']);

        $this->taxRepository->merge($versionId->toString(), $liveVersionContext);

        $basic = $this->taxRepository->readBasic([$uuid->toString()], $liveVersionContext);
        $this->assertCount(1, $basic);
        $this->assertTrue($basic->has($uuid->toString()));
        $tax = $basic->get($uuid->toString());
        $this->assertEquals('new merged name', $tax->getName());

        $basic = $this->taxRepository->readBasic([$uuid->toString()], $versionContext);
        $this->assertCount(1, $basic);

        $row = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString($versionId)->getBytes()
        ]);
        $this->assertEmpty($row);
    }

    public function testSearcherConsidersVersionFallback()
    {
        $uuid = Uuid::uuid4();
        $liveVersionContext = TranslationContext::createDefaultContext();
        $taxData = ['id' => $uuid->toString(), 'name' => 'foo tax', 'rate' => 5];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $versionId = $this->taxRepository->createVersion($uuid->toString(), $liveVersionContext, 'testMerge version');
        $versionId = Uuid::fromString($versionId);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString($versionId)->getBytes()
        ]);
        $this->assertNotEmpty($tax);
        $this->assertEquals(5, $tax['tax_rate']);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);
        $this->taxRepository->update([['id' => $uuid->toString(), 'name' => 'new merged name', 'rate' => 4]], $versionContext);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $uuid->getBytes(),
            'version' => Uuid::fromString($versionId)->getBytes()
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

    public function testOneToManyVersioning()
    {
        $uuid = Uuid::uuid4();
        $liveVersionContext = TranslationContext::createDefaultContext();
        $taxData = [
            'id' => $uuid->toString(),
            'name' => 'foo tax',
            'rate' => 5,
            'areaRules' => [
                [
                    'id' => $uuid->toString(),
                    'taxRate' => 6,
                    'name' => 'test',
                    'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP
                ]
            ]
        ];
        $this->taxRepository->create([$taxData], $liveVersionContext);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);

        $changes = $this->getVersionData(TaxAreaRuleDefinition::getEntityName(), $uuid->toString(), Defaults::LIVE_VERSION);
        $this->assertCount(1, $changes);

        $versionId = $this->taxRepository->createVersion($uuid->toString(), $liveVersionContext);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), $versionId);
        $this->assertCount(1, $changes);

        $changes = $this->getVersionData(TaxAreaRuleDefinition::getEntityName(), $uuid->toString(), $versionId);
        $this->assertCount(1, $changes);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);

        $this->taxRepository->upsert([
            [
                'id' => $uuid->toString(),
                'rate' => 15,
                'areaRules' => [
                    ['id' => $uuid->toString(), 'taxRate' => 16]
                ]
            ]
        ], $versionContext);

        $changes = $this->getVersionData(TaxDefinition::getEntityName(), $uuid->toString(), $versionId);
        $this->assertCount(2, $changes);

        $changes = $this->getVersionData(TaxAreaRuleDefinition::getEntityName(), $uuid->toString(), $versionId);
        $this->assertCount(2, $changes);

        $liveTax = $this->taxRepository->readDetail([$uuid->toString()], $liveVersionContext);
        $this->assertCount(1, $liveTax);
        $this->assertTrue($liveTax->has($uuid->toString()));
        $tax = $liveTax->get($uuid->toString());

        /** @var TaxDetailStruct $tax */
        $this->assertEquals(5, $tax->getRate());
        $this->assertCount(1, $tax->getAreaRules());
        $this->assertEquals(6, $tax->getAreaRules()->get($uuid->toString())->getTaxRate());

        $versionTax = $this->taxRepository->readDetail([$uuid->toString()], $versionContext);
        $this->assertCount(1, $versionTax);
        $this->assertTrue($versionTax->has($uuid->toString()));
        $tax = $versionTax->get($uuid->toString());

        /** @var TaxDetailStruct $tax */
        $this->assertEquals(15, $tax->getRate());
        $this->assertCount(1, $tax->getAreaRules());
        $this->assertEquals(16, $tax->getAreaRules()->get($uuid->toString())->getTaxRate());

        $this->taxRepository->merge($versionId, $liveVersionContext);

        $liveTax = $this->taxRepository->readDetail([$uuid->toString()], $liveVersionContext);
        $this->assertCount(1, $liveTax);
        $this->assertTrue($liveTax->has($uuid->toString()));
        $tax = $liveTax->get($uuid->toString());

        /** @var TaxDetailStruct $tax */
        $this->assertEquals(15, $tax->getRate());
        $this->assertCount(1, $tax->getAreaRules());
        $this->assertEquals(16, $tax->getAreaRules()->get($uuid->toString())->getTaxRate());

        $liveTax = $this->taxRepository->readDetail([$uuid->toString()], $versionContext);
        $this->assertCount(1, $liveTax);
        $this->assertTrue($liveTax->has($uuid->toString()));
        $tax = $liveTax->get($uuid->toString());

        /** @var TaxDetailStruct $tax */
        $this->assertEquals(15, $tax->getRate());
        $this->assertCount(1, $tax->getAreaRules());
        $this->assertEquals(16, $tax->getAreaRules()->get($uuid->toString())->getTaxRate());
    }

    public function testVersioningWithProductInheritance()
    {
        $productId = Uuid::uuid4();
        $variantId = Uuid::uuid4();

        $products = [
            [
                'id' => $productId->toString(),
                'name' => 'parent',
                'price' => 10,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['rate' => 18, 'name' => 'test']
            ],
            [
                'id' => $variantId->toString(),
                'price' => 15,
                'parentId' => $productId->toString()
            ]
        ];
        $liveContext = TranslationContext::createDefaultContext();
        $this->productRepository->create($products, $liveContext);

        $variantVersionId = $this->productRepository->createVersion($variantId->toString(), $liveContext);
        $versionContext = $liveContext->createWithVersionId($variantVersionId);

        $this->productRepository->update([
            ['id' => $variantId->toString(), 'price' => 20]
        ], $versionContext);

        $variant = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => $variantId->getBytes(),
            'version' => Uuid::fromString($variantVersionId)->getBytes()
        ]);

        $this->assertEquals(20, $variant['price']);

        $variants = $this->productRepository->readBasic([$variantId->toString()], $versionContext);
        $this->assertCount(1, $variants);
        $this->assertTrue($variants->has($variantId->toString()));

        $variant = $variants->get($variantId->toString());
        $this->assertEquals(20, $variant->getPrice());
        $this->assertEquals('parent', $variant->getName());

        $this->productRepository->createVersion($productId->toString(), $liveContext, 'test parent', $variantVersionId);

        $this->productRepository->update([
            ['id' => $productId->toString(), 'name' => 'parent version', 'price' => 25]
        ], $versionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId->toString(), $variantVersionId);
        $this->assertCount(2, $changes);

        $changes = $this->getTranslationVersionData(ProductTranslationDefinition::getEntityName(), Defaults::SHOP, 'productId', $productId->toString(), $variantVersionId);
        $this->assertCount(2, $changes);

        $product = $this->connection->fetchAssoc(
            'SELECT * FROM product_translation WHERE product_id = :id AND version_id = :version AND language_id = :language',
            [
                'id' => $productId->getBytes(),
                'version' => Uuid::fromString($variantVersionId)->getBytes(),
                'language' => Uuid::fromString($versionContext->getShopId())->getBytes()
            ]
        );
        $this->assertEquals('parent version', $product['name']);

        $variants = $this->productRepository->readBasic([$productId->toString()], $versionContext);
        $this->assertCount(1, $variants);
        $this->assertTrue($variants->has($productId->toString()));

        $variant = $variants->get($productId->toString());
        $this->assertEquals(25, $variant->getPrice());
        $this->assertEquals('parent version', $variant->getName());

        $variants = $this->productRepository->readBasic([$variantId->toString()], $versionContext);
        $this->assertCount(1, $variants);
        $this->assertTrue($variants->has($variantId->toString()));

        $variant = $variants->get($variantId->toString());
        $this->assertEquals(20, $variant->getPrice());
        $this->assertEquals('parent version', $variant->getName());
    }

    public function testTaxRestrictions()
    {
        $id = Uuid::uuid4();

        $liveContext = TranslationContext::createDefaultContext();

        $this->taxRepository->create([['id' => $id->toString(), 'name' => 'test', 'rate' => 15]], $liveContext);

        $this->productRepository->create([
            [
                'id' => $id->toString(),
                'name' => 'Test',
                'price' => 15,
                'taxId' => $id->toString(),
                'manufacturer' => ['name' => 'test']
            ]
        ], $liveContext);

        $versionId = $this->taxRepository->createVersion($id->toString(), $liveContext);

        $versionContext = $liveContext->createWithVersionId($versionId);

        $this->taxRepository->update([
            ['id' => $id->toString(), 'rate' => 19]
        ], $versionContext);

        $this->taxRepository->merge($versionId, $liveContext);

        $tax = $this->connection->fetchAssoc('SELECT * FROM tax WHERE id = :id AND version_id = :version', [
            'id' => $id->getBytes(),
            'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()
        ]);

        $this->assertEquals(19, $tax['tax_rate']);
    }

    public function testCampaign()
    {
        $product1 = Uuid::uuid4();
        $product2 = Uuid::uuid4();

        $parentCategory = $this->connection->fetchColumn(
            'SELECT id FROM category WHERE parent_id = :main',
            ['main' => Uuid::fromString(Defaults::ROOT_CATEGORY)->getBytes()]
        );
        $parentCategory = Uuid::fromBytes($parentCategory);

        $category = Uuid::uuid4()->toString();
        $versionId = Uuid::uuid4()->toString();

        $products = [
            [
                'id' => $product1->toString(),
                'name' => 'product test',
                'price' => 10,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 19],
                'categories' => [
                    ['category' => ['id' => $category, 'parentId' => $parentCategory, 'name' => 'TEST cat']]
                ]
            ],[
                'id' => $product2->toString(),
                'name' => 'product test',
                'price' => 10,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'rate' => 19],
                'categories' => [
                    ['categoryId' => $category]
                ]
            ]
        ];

        $liveContext = TranslationContext::createDefaultContext();
        $this->productRepository->create($products, $liveContext);

        $this->productRepository->createVersion($product1->toString(), $liveContext, 'Campaign', $versionId);
        $versionContext = $liveContext->createWithVersionId($versionId);
        $update = ['id' => $product1->toString(), 'price' => 100];
        $this->productRepository->update([$update], $versionContext);

        $versionId = $this->productRepository->createVersion($product2->toString(), $liveContext, 'Campaign', $versionId);

        $versionContext = $liveContext->createWithVersionId($versionId);
        $update = ['id' => $product2->toString(), 'price' => 200];
        $this->productRepository->update([$update], $versionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1->toString(), $versionId);
        $this->assertCount(2, $changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->toString(), $versionId);
        $this->assertCount(2, $changes);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.categories.id', $category));
        $criteria->addFilter(new RangeQuery('product.price', [RangeQuery::GTE => 100]));

        $search = $this->productRepository->searchIds($criteria, $versionContext);
        $this->assertCount(2, $search->getIds());
        $this->assertContains($product1->toString(), $search->getIds());
        $this->assertContains($product2->toString(), $search->getIds());

        $notExisting = Uuid::uuid4()->toString();
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
        $this->assertContains($product1->toString(), $search->getIds());
        $this->assertContains($product2->toString(), $search->getIds());

        $search = $this->productRepository->searchIds($criteria, $notExistingContext);
        $this->assertCount(2, $search->getIds());
        $this->assertContains($product1->toString(), $search->getIds());
        $this->assertContains($product2->toString(), $search->getIds());

        //MERGE
        $this->productRepository->merge($versionId, $liveContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1->toString(), $versionId);
        $this->assertEmpty($changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->toString(), $versionId);
        $this->assertEmpty($changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1->toString(), Defaults::LIVE_VERSION);
        $this->assertCount(2, $changes);
        $this->assertEquals('insert', $changes[0]['action']);
        $this->assertEquals('update', $changes[1]['action']);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->toString(), Defaults::LIVE_VERSION);
        $this->assertCount(2, $changes);
        $this->assertEquals('insert', $changes[0]['action']);
        $this->assertEquals('update', $changes[1]['action']);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2->toString(), $versionId);
        $this->assertEmpty($changes);

        $product = $this->connection->fetchAssoc(
            'SELECT * FROM product WHERE id = :id AND version_id = :version',
            ['id' => $product1->getBytes(), 'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()]
        );
        $this->assertEquals(100, $product['price']);

        $product = $this->connection->fetchAssoc(
            'SELECT * FROM product WHERE id = :id AND version_id = :version',
            ['id' => $product2->getBytes(), 'version' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes()]
        );
        $this->assertEquals(200, $product['price']);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.categories.id', $category));
        $criteria->addFilter(new RangeQuery('product.price', [RangeQuery::GTE => 100]));

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        $this->assertCount(2, $search->getIds());
        $this->assertContains($product1->toString(), $search->getIds());
        $this->assertContains($product2->toString(), $search->getIds());
    }

    private function getVersionData(string $entity, string $id, string $versionId): array
    {
        return $this->connection->fetchAll(
            "SELECT d.* 
             FROM version_commit_data d
             INNER JOIN version_commit c
               ON c.id = d.version_commit_id
               AND c.version_id = :version
             WHERE entity_name = :entity 
             AND JSON_EXTRACT(entity_id, '$.id') = :id
             ORDER BY ai",
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromString($versionId)->getBytes()
            ]
        );
    }

    private function getTranslationVersionData(string $entity, string $languageId, string $foreignKeyName, string $foreignKey, string $versionId): array
    {
        return $this->connection->fetchAll(
            "SELECT * 
             FROM version_commit_data 
             WHERE entity_name = :entity 
             AND JSON_EXTRACT(entity_id, '$.".$foreignKeyName."') = :id
             AND JSON_EXTRACT(entity_id, '$.languageId') = :language
             AND JSON_EXTRACT(entity_id, '$.versionId') = :version
             ORDER BY ai",
            [
                'entity' => $entity,
                'id' => $foreignKey,
                'language' => $languageId,
                'version' => $versionId
            ]
        );
    }
}

