<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Version;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Test\TaxFixtures;

class VersioningTest extends TestCase
{
    use IntegrationTestBehaviour, TaxFixtures;

    /**
     * @var RepositoryInterface
     */
    private $productRepository;

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

    public function testVersionCommitOnInsert(): void
    {
        $uuid = Uuid::uuid4()->getHex();
        $manufacturerUuid = Uuid::uuid4()->getHex();
        $taxUuid = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();
        $productData = [
            'id' => $uuid,
            'ean' => '123',
            'name' => 'Cat of Doom',
            'tax' => ['id' => $taxUuid, 'taxRate' => 12, 'name' => 'mwst'],
            'manufacturer' => ['id' => $manufacturerUuid, 'name' => 'shopware'],
        ];

        $this->productRepository->create([$productData], $context);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $uuid, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes, sprintf('Change for entity_id "%s" was not created.', $uuid));

        $change = array_shift($changes);

        $productData['versionId'] = Defaults::LIVE_VERSION;

        $payload = json_decode($change['payload'], true);

        $compareArray = [
            'id' => true,
            'ean' => true,
            'versionId' => true,
        ];

        static::assertEquals(array_intersect_key($productData, $compareArray), array_intersect_key($payload, $compareArray));
    }

    public function testCreateNewVersion(): void
    {
        $uuid = Uuid::uuid4()->getHex();
        $manufacturerUuid = Uuid::uuid4()->getHex();
        $taxUuid = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();
        $productData = [
            'id' => $uuid,
            'ean' => '123',
            'name' => 'Cat of Doom',
            'tax' => ['id' => $taxUuid, 'taxRate' => 12, 'name' => 'mwst'],
            'manufacturer' => ['id' => $manufacturerUuid, 'name' => 'shopware'],
        ];

        $this->productRepository->create([$productData], $context);

        $versionId = $this->productRepository->createVersion($uuid, $context, 'testCreateVersionWithoutRelations version');

        static::assertNotEmpty($versionId);

        $product = $this->connection->fetchAssoc(
            'SELECT * FROM product WHERE id = :id AND version_id = :versionId',
            [
                'id' => Uuid::fromStringToBytes($uuid),
                'versionId' => Uuid::fromHexToBytes($versionId),
            ]
        );

        static::assertNotFalse($product, 'Product clone was not created.');

        static::assertEquals(Uuid::fromHexToBytes($uuid), $product['id']);
        static::assertEquals(Uuid::fromHexToBytes($versionId), $product['version_id']);
        static::assertEquals('123', $product['ean']);

        $productOrg = $this->connection->fetchAssoc(
            'SELECT * FROM product WHERE id = :id AND version_id = :versionId',
            [
                'id' => Uuid::fromStringToBytes($uuid),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        static::assertNotFalse($productOrg, 'Product clone was not created.');

        static::assertEquals(Uuid::fromHexToBytes($uuid), $productOrg['id']);
        static::assertEquals(Uuid::fromHexToBytes(Defaults::LIVE_VERSION), $productOrg['version_id']);
        static::assertEquals('123', $productOrg['ean']);
    }

    public function testCreateNewVersionWithSubresources(): void
    {
        $context = Context::createDefaultContext();

        $productId = Uuid::uuid4()->getHex();
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            [
                'id' => $mediaId,
                'name' => 'test_media',
                'extension' => '.jpg',
            ],
        ];

        $productData = [
            [
                'id' => $productId,
                'name' => 'parent',
                'ean' => '4711',
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
                'media' => [
                    ['mediaId' => $mediaId],
                ],
            ],
        ];

        $liveContext = Context::createDefaultContext();
        $this->mediaRepository->create($mediaData, $liveContext);
        $this->productRepository->create($productData, $liveContext);

        $versionId = $this->productRepository->createVersion($productId, $context, 'testCreateVersionWithSubresources version');

        static::assertNotEmpty($versionId);

        $product = $this->connection->fetchAssoc(
            'SELECT * FROM product WHERE id = :id AND version_id = :versionId',
            [
                'id' => Uuid::fromHexToBytes($productId),
                'versionId' => Uuid::fromHexToBytes($versionId),
            ]
        );

        $media = $this->connection->fetchAll(
            'SELECT * FROM product_media WHERE product_id = :id AND version_id = :versionId',
            [
                'id' => Uuid::fromHexToBytes($productId),
                'versionId' => Uuid::fromHexToBytes($versionId),
            ]
        );

        static::assertNotFalse($product, 'Product clone was not created.');
        static::assertCount(1, $media, 'Product Media clones were not created.');

        static::assertEquals(Uuid::fromHexToBytes($productId), $product['id']);
        static::assertEquals(Uuid::fromHexToBytes($versionId), $product['version_id']);
        static::assertEquals($productData[0]['ean'], $product['ean']);

        static::assertEquals(Uuid::fromHexToBytes($productId), $media[0]['product_id']);
        static::assertEquals(Uuid::fromHexToBytes($versionId), $media[0]['version_id']);
    }

    public function testMergeVersions(): void
    {
        $context = Context::createDefaultContext();

        $productId = Uuid::uuid4()->getHex();
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            [
                'id' => $mediaId,
                'name' => 'test_media',
                'extension' => '.jpg',
            ],
        ];

        $productData = [
            [
                'id' => $productId,
                'name' => 'parent',
                'ean' => '4711',
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
                'media' => [
                    ['mediaId' => $mediaId],
                ],
            ],
        ];

        $liveContext = Context::createDefaultContext();
        $this->mediaRepository->create($mediaData, $liveContext);
        $this->productRepository->create($productData, $liveContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $versionId = $this->productRepository->createVersion($productId, $context, 'testMerge version');

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, $versionId);
        static::assertCount(1, $changes);
        static::assertEquals('clone', $changes[0]['action']);

        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->update([['id' => $productId, 'ean' => 'new merged ean']], $versionContext);

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($productId),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);
        static::assertEquals('4711', $row['ean']);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, $versionId);
        static::assertCount(2, $changes);
        static::assertEquals('clone', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);

        $this->productRepository->merge($versionId, $context);

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($productId),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        static::assertEquals('new merged ean', $row['ean']);

        $row = $this->connection->fetchAssoc('SELECT * FROM version WHERE id = :id', ['id' => Uuid::fromHexToBytes($versionId)]);
        static::assertEmpty($row);

        $row = $this->connection->fetchAssoc('SELECT * FROM version_commit WHERE version_id = :id', ['id' => Uuid::fromHexToBytes($versionId)]);
        static::assertEmpty($row);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, $versionId);
        static::assertEmpty($changes);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, Defaults::LIVE_VERSION);
        static::assertCount(2, $changes);

        static::assertEquals('insert', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);
    }

    public function testReadConsiderVersion(): void
    {
        $liveVersionContext = Context::createDefaultContext();

        $productId = Uuid::uuid4()->getHex();
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            [
                'id' => $mediaId,
                'name' => 'test_media',
                'extension' => '.jpg',
            ],
        ];

        $productData = [
            [
                'id' => $productId,
                'name' => 'parent',
                'ean' => '4711',
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
                'media' => [
                    ['mediaId' => $mediaId],
                ],
            ],
        ];

        $liveContext = Context::createDefaultContext();
        $this->mediaRepository->create($mediaData, $liveContext);
        $this->productRepository->create($productData, $liveContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $versionId = $this->productRepository->createVersion($productId, $liveVersionContext, 'testMerge version');

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, $versionId);
        static::assertCount(1, $changes);
        static::assertEquals('clone', $changes[0]['action']);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);
        $this->productRepository->update([['id' => $productId, 'ean' => 'new merged ean']], $versionContext);

        $basic = $this->productRepository->read(new ReadCriteria([$productId]), $liveVersionContext);
        static::assertCount(1, $basic);
        static::assertTrue($basic->has($productId));
        $product = $basic->get($productId);
        static::assertEquals('4711', $product->getEan());

        $basic = $this->productRepository->read(new ReadCriteria([$productId]), $versionContext);
        static::assertCount(1, $basic);
        static::assertTrue($basic->has($productId));
        $product = $basic->get($productId);
        static::assertEquals('new merged ean', $product->getEan());

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($productId),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);
        static::assertEquals('4711', $row['ean']);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, $versionId);
        static::assertCount(2, $changes);
        static::assertEquals('clone', $changes[0]['action']);
        static::assertEquals('update', $changes[1]['action']);

        $this->productRepository->merge($versionId, $liveVersionContext);

        $basic = $this->productRepository->read(new ReadCriteria([$productId]), $liveVersionContext);
        static::assertCount(1, $basic);
        static::assertTrue($basic->has($productId));
        $product = $basic->get($productId);
        static::assertEquals('new merged ean', $product->getEan());

        $basic = $this->productRepository->read(new ReadCriteria([$productId]), $versionContext);
        static::assertCount(1, $basic);

        $row = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($productId),
            'version' => Uuid::fromHexToBytes($versionId),
        ]);
        static::assertEmpty($row);
    }

    public function testSearcherConsidersVersionFallback(): void
    {
        $liveVersionContext = Context::createDefaultContext();

        $productId = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            [
                'id' => $mediaId,
                'name' => 'test_media',
                'extension' => '.jpg',
            ],
        ];

        $productData = [
            [
                'id' => $productId,
                'name' => 'parent',
                'ean' => '4711' . $productId,
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
                'media' => [
                    ['mediaId' => $mediaId],
                ],
            ],
        ];

        $liveContext = Context::createDefaultContext();
        $this->mediaRepository->create($mediaData, $liveContext);
        $this->productRepository->create($productData, $liveContext);

        $versionId = $this->productRepository->createVersion($productId, $liveVersionContext, 'testMerge version');

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($productId),
            'version' => Uuid::fromHexToBytes($versionId),
        ]);
        static::assertNotEmpty($product);
        static::assertEquals('4711' . $productId, $product['ean']);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);

        $this->productRepository->update([['id' => $productId, 'ean' => 'new merged ean' . $productId]], $versionContext);

        $product = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($productId),
            'version' => Uuid::fromHexToBytes($versionId),
        ]);
        static::assertNotEmpty($product);
        static::assertEquals('new merged ean' . $productId, $product['ean']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('ean', 'new merged ean' . $productId));

        $result = $this->productRepository->searchIds($criteria, $liveVersionContext);
        static::assertEquals(0, $result->getTotal());

        $result = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertEquals(1, $result->getTotal());

        $productData = [
            [
                'id' => $productId2,
                'name' => 'parent',
                'ean' => 'new merged ean' . $productId,
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
                'media' => [
                    ['mediaId' => $mediaId],
                ],
            ],
        ];
        $this->productRepository->create($productData, $liveVersionContext);

        $result = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertEquals(2, $result->getTotal());

        $result = $this->productRepository->searchIds($criteria, $liveVersionContext);
        static::assertEquals(1, $result->getTotal());
    }

    public function testOneToManyVersioning(): void
    {
        $this->markTestSkipped('Version Mapping broken see: NEXT/repos/platform/pull-requests/533/');
        $liveVersionContext = Context::createDefaultContext();

        $productId = Uuid::uuid4()->getHex();
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            [
                'id' => $mediaId,
                'name' => 'test_media',
                'extension' => '.jpg',
            ],
        ];

        $productData = [
            [
                'id' => $productId,
                'name' => 'parent',
                'ean' => '4711',
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
                'media' => [
                    ['mediaId' => $mediaId],
                ],
            ],
        ];

        $this->mediaRepository->create($mediaData, $liveVersionContext);
        $this->productRepository->create($productData, $liveVersionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, Defaults::LIVE_VERSION);
        static::assertCount(1, $changes);

        $versionId = $this->productRepository->createVersion($productId, $liveVersionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, $versionId);
        static::assertCount(1, $changes);

        $versionContext = $liveVersionContext->createWithVersionId($versionId);

        $criteria = new ReadCriteria([$productId]);
        $criteria->addAssociation('media');

        $liveProduct = $this->productRepository->read($criteria, $liveVersionContext);
        $productMediaId = $liveProduct->first()->getMedia()->first()->getId();
        $this->productRepository->upsert([
            [
                'id' => $productId,
                'name' => 'parent',
                'ean' => '4711',
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
            ],
        ], $versionContext);

        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $productId, $versionId);

        static::assertCount(2, $changes);

        $changes = $this->getVersionData(ProductMediaDefinition::getEntityName(), $productMediaId, $versionId);

        static::assertCount(1, $changes);

        $criteria = new ReadCriteria([$productId]);
        $criteria->addAssociation('media');

        $liveProduct = $this->productRepository->read($criteria, $liveVersionContext);
        static::assertCount(1, $liveProduct);
        static::assertTrue($liveProduct->has($productId));
        $product = $liveProduct->get($productId);

        /* @var ProductStruct $product */
        static::assertEquals('4711', $product->getEan());
        static::assertCount(1, $product->getMedia());

        $criteria = new ReadCriteria([$productId]);
        $criteria->addAssociation('media');
        $versionProduct = $this->productRepository->read($criteria, $versionContext);
        static::assertCount(1, $versionProduct);
        static::assertTrue($versionProduct->has($productId));
        $product = $versionProduct->get($productId);

        /* @var ProductStruct $product */
        static::assertEquals('4711', $product->getEan());
        static::assertCount(1, $product->getMedia());

        $this->productRepository->merge($versionId, $liveVersionContext);

        $liveProduct = $this->productRepository->read($criteria, $liveVersionContext);
        static::assertCount(1, $liveProduct);
        static::assertTrue($liveProduct->has($productId));
        $product = $liveProduct->get($productId);

        /* @var ProductStruct $product */
        static::assertEquals('4711', $product->getEan());
        static::assertCount(1, $product->getMedia());

        $liveProduct = $this->productRepository->read($criteria, $versionContext);
        static::assertCount(1, $liveProduct);
        static::assertTrue($liveProduct->has($productId));
        $product = $liveProduct->get($productId);

        /* @var ProductStruct $product */
        static::assertEquals(2, $product->getEan());
        static::assertCount(1, $product->getMedia());
    }

    public function testVersioningWithProductInheritance(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $variantId = Uuid::uuid4()->getHex();
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            [
                'id' => $mediaId,
                'name' => 'test_media',
                'extension' => '.jpg',
            ],
        ];

        $products = [
            [
                'id' => $productId,
                'name' => 'parent',
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
                'media' => [
                        ['mediaId' => $mediaId],
                    ],
            ],
            [
                'id' => $variantId,
                'price' => ['gross' => 15, 'net' => 14, 'linked' => false],
                'parentId' => $productId,
            ],
        ];

        $liveContext = Context::createDefaultContext();
        $this->mediaRepository->create($mediaData, $liveContext);
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

    public function testVersioningWithProductPriceRules(): void
    {
        $this->markTestSkipped('Should be fixed with NEXT-1151');
        $productId = Uuid::uuid4()->getHex();
        $variantId = Uuid::uuid4()->getHex();
        $ruleId = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $productId,
                'name' => 'parent',
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
                'priceRules' => [
                    [
                        'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                        'rule' => ['id' => $ruleId, 'name' => 'test', 'priority' => 1, 'payload' => new AndRule()],
                        'currencyId' => Defaults::CURRENCY,
                        'quantityStart' => 0,
                    ],
                ],
            ],
            [
                'id' => $variantId,
                'price' => ['gross' => 15, 'net' => 14, 'linked' => false],
                'parentId' => $productId,
            ],
        ];

        $liveContext = Context::createDefaultContext();
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

    public function testMergeBoolField(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $variantId = Uuid::uuid4()->getHex();
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            [
                'id' => $mediaId,
                'name' => 'test_media',
                'extension' => '.jpg',
            ],
        ];

        $productData = [
            [
                'id' => $productId,
                'name' => 'parent',
                'ean' => '4711',
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 18, 'name' => 'test'],
                'active' => true,
                'media' => [
                    ['mediaId' => $mediaId],
                ],
            ],
            [
                'id' => $variantId,
                'price' => ['gross' => 15, 'net' => 14, 'linked' => false],
                'parentId' => $productId,
            ],
        ];

        $liveContext = Context::createDefaultContext();
        $this->mediaRepository->create($mediaData, $liveContext);
        $this->productRepository->create($productData, $liveContext);

        $versionId = $this->productRepository->createVersion($productId, $liveContext, 'boolVersionUpdate');

        $versionContext = $liveContext->createWithVersionId($versionId);

        $update = ['id' => $productId, 'active' => false];
        $this->productRepository->update([$update], $versionContext);

        //Fails because the mergeCall tries to convert the serialized number 0/1 to boolean (also see NEXT-670)
        $this->productRepository->merge($versionId, $liveContext);

        $category = $this->connection->fetchAssoc('SELECT * FROM product WHERE id = :id AND version_id = :version', [
            'id' => Uuid::fromHexToBytes($productId),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        static::assertEquals(0, $category['active']);

        $fetchedCategory = $this->productRepository->read(new ReadCriteria([$productId]), $liveContext)->get($productId);
        static::assertEquals(false, $fetchedCategory->get('active'));
    }

    public function testMergeDateTimeField(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $versionId = Uuid::uuid4()->getHex();
        $mediaId = Uuid::uuid4()->getHex();

        $mediaData = [
            [
                'id' => $mediaId,
                'name' => 'test_media',
                'extension' => '.jpg',
            ],
        ];

        $productData = [
            [
                'id' => $productId,
                'name' => 'parent',
                'ean' => '4711',
                'price' => ['gross' => 15, 'net' => 12, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'releaseDate' => new \DateTime(),
                'tax' => ['taxRate' => 18, 'name' => 'test'],
                'active' => true,
                'media' => [
                    ['mediaId' => $mediaId],
                ],
            ],
        ];

        $liveContext = Context::createDefaultContext();
        $this->mediaRepository->create($mediaData, $liveContext);
        $this->productRepository->create($productData, $liveContext);
        $this->productRepository->createVersion($productId, $liveContext, 'dateVersionUpdate', $versionId);

        $versionContext = $liveContext->createWithVersionId($versionId);
        $updateTime = (new \DateTime())->add(new \DateInterval('P2Y4DT6H8M'));

        $update = [
            'id' => $productId,
            'releaseDate' => $updateTime->format(Defaults::DATE_FORMAT),
        ];

        $this->productRepository->update([$update], $versionContext);

        $this->productRepository->merge($versionId, $liveContext);

        /** @var ProductStruct $product */
        $product = $this->productRepository->read(new ReadCriteria([$productId]), $liveContext)
            ->get($productId);

        static::assertEquals($updateTime->format(Defaults::DATE_FORMAT), $product->getReleaseDate()->format(Defaults::DATE_FORMAT));
    }

    public function testMergeCalculatedField(): void
    {
        $liveContext = Context::createDefaultContext();

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

        static::assertEquals(4, \count($updatedCategories));

        static::assertContains($categories[2]['id'], $updatedCategories);
        static::assertContains($categories[3]['id'], $updatedCategories);
    }

    public function testCampaign(): void
    {
        $liveContext = Context::createDefaultContext();

        $parentCategoryId = $this->createCategory($liveContext);

        $product1 = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();

        $category = Uuid::uuid4()->getHex();
        $versionId = Uuid::uuid4()->getHex();

        $taxId1 = Uuid::uuid4()->getHex();
        $taxId2 = Uuid::uuid4()->getHex();

        $taxId119 = Uuid::uuid4()->getHex();
        $taxId225 = Uuid::uuid4()->getHex();

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

        //Create Live Version of two Products
        $this->productRepository->create($products, $liveContext);

        //Create Taxes
        $this->taxRepository->create(
            [
                ['id' => $taxId119, 'name' => 'test2', 'taxRate' => 19],
                ['id' => $taxId225, 'name' => 'test3', 'taxRate' => 25],
            ],
            $liveContext
        );

        //Clone Product1 to new Version "Campaign"
        $this->productRepository->createVersion($product1, $liveContext, 'Campaign', $versionId);

        //New Context for Version2
        $versionContext = $liveContext->createWithVersionId($versionId);

        //Update Product1 in Version2
        $update = ['id' => $product1, 'taxId' => $taxId119];
        $this->productRepository->update([$update], $versionContext);

        //Clone Product1 to new Version "Campaign"
        $versionId = $this->productRepository->createVersion($product2, $liveContext, 'Campaign', $versionId);

        //Update Product2 in Version "Campaign"
        $update = ['id' => $product2, 'taxId' => $taxId225];
        $this->productRepository->update([$update], $versionContext);

        //Get Changes for Product1 (should be 2 (clone and update))
        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1, $versionId);
        static::assertCount(2, $changes);

        //Get Changes for Product2 (should be 2 (clone and update))
        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2, $versionId);
        static::assertCount(2, $changes);

        //Get Products with given categorie and taxRate >= 19 (The two updated Products)
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.id', $category));
        $criteria->addFilter(new RangeFilter('product.tax.taxRate', [RangeFilter::GTE => 19]));

        $search = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1, $search->getIds());
        static::assertContains($product2, $search->getIds());

        //Create new Context with not existing Version
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

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.id', $category));
        $criteria->addFilter(new RangeFilter('product.tax.taxRate', [RangeFilter::GTE => 19]));

        $search = $this->productRepository->searchIds($criteria, $liveContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1, $search->getIds());
        static::assertContains($product2, $search->getIds());
    }

    public function testCampaignWithCombinedUpdates(): void
    {
        $this->markTestSkipped('Failes cause of versionCommits to unversioned Entities. Fix with NEXT-1159');
        $liveContext = Context::createDefaultContext();

        $parentCategoryId = $this->createCategory($liveContext);

        $product1 = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();

        $category = Uuid::uuid4()->getHex();
        $versionId = Uuid::uuid4()->getHex();

        $taxId1 = Uuid::uuid4()->getHex();
        $taxId2 = Uuid::uuid4()->getHex();

        $taxId119 = Uuid::uuid4()->getHex();
        $taxId225 = Uuid::uuid4()->getHex();

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

        //Create Live Version of two Products
        $this->productRepository->create($products, $liveContext);

        //Clone Product1 to new Version "Campaign"
        $this->productRepository->createVersion($product1, $liveContext, 'Campaign', $versionId);

        //New Context for Version2
        $versionContext = $liveContext->createWithVersionId($versionId);

        //Update Product1 in Version2
        $update = ['id' => $product1, 'tax' => ['id' => $taxId119, 'name' => 'test2', 'taxRate' => 19]];
        $this->productRepository->upsert([$update], $versionContext);

        //Clone Product1 to new Version "Campaign"
        $versionId = $this->productRepository->createVersion($product2, $liveContext, 'Campaign', $versionId);

        //Update Product2 in Version "Campaign"
        $update = ['id' => $product2, 'tax' => ['id' => $taxId225, 'name' => 'test3', 'taxRate' => 25]];
        $this->productRepository->upsert([$update], $versionContext);

        //Get Changes for Product1 (should be 2 (clone and update))
        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product1, $versionId);
        static::assertCount(2, $changes);

        //Get Changes for Product2 (should be 2 (clone and update))
        $changes = $this->getVersionData(ProductDefinition::getEntityName(), $product2, $versionId);
        static::assertCount(2, $changes);

        //Get Products with given categorie and taxRate >= 19 (The two updated Products)
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.id', $category));
        $criteria->addFilter(new RangeFilter('product.tax.taxRate', [RangeFilter::GTE => 19]));

        $search = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertCount(2, $search->getIds());
        static::assertContains($product1, $search->getIds());
        static::assertContains($product2, $search->getIds());

        //Create new Context with not existing Version
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

        //MERGE Failes because of NEXT-1159
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
             WHERE entity_name = :entity 
             AND JSON_EXTRACT(entity_id, '$.id') = :id
             ORDER BY auto_increment",
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromHexToBytes($versionId),
            ]
        );
    }

    private function getTranslationVersionData(string $entity, string $languageId, string $foreignKeyName, string $foreignKey, string $versionId): array
    {
        return $this->connection->fetchAll(
            "SELECT * 
             FROM version_commit_data 
             WHERE entity_name = :entity
             AND JSON_EXTRACT(entity_id, '$." . $foreignKeyName . "') = :id
             AND JSON_EXTRACT(entity_id, '$.languageId') = :language
             AND JSON_EXTRACT(entity_id, '$.versionId') = :version
             ORDER BY auto_increment",
            [
                'entity' => $entity,
                'id' => $foreignKey,
                'language' => $languageId,
                'version' => $versionId,
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
