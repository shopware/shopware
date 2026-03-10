<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class ProductSubscriberBeforeDeleteIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testBeforeDeleteProductCleansUpConfiguratorSettingsWhenLastVariantDeleted(): void
    {
        $ids = new IdsCollection();

        $propertyGroupId = $ids->create('property-group');
        $option1Id = $ids->create('option-1');
        $option2Id = $ids->create('option-2');

        static::getContainer()->get('property_group.repository')->create([
            [
                'id' => $propertyGroupId,
                'name' => 'Color',
                'options' => [
                    ['id' => $option1Id, 'name' => 'Red'],
                    ['id' => $option2Id, 'name' => 'Blue'],
                ],
            ],
        ], $this->context);

        $parentId = $ids->create('parent');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $parentId,
                'name' => 'Parent Product',
                'productNumber' => 'PARENT-001',
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'configuratorSettings' => [
                    [
                        'id' => $ids->create('config-setting-1'),
                        'optionId' => $option1Id,
                    ],
                    [
                        'id' => $ids->create('config-setting-2'),
                        'optionId' => $option2Id,
                    ],
                ],
            ],
        ], $this->context);

        $variant1Id = $ids->create('variant-1');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $variant1Id,
                'parentId' => $parentId,
                'name' => 'Variant 1',
                'productNumber' => 'VAR-001',
                'stock' => 5,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'options' => [
                    ['id' => $option1Id],
                ],
            ],
        ], $this->context);

        $variant2Id = $ids->create('variant-2');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $variant2Id,
                'parentId' => $parentId,
                'name' => 'Variant 2',
                'productNumber' => 'VAR-002',
                'stock' => 5,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'options' => [
                    ['id' => $option2Id],
                ],
            ],
        ], $this->context);

        $configSettingsBefore = $this->connection->fetchAllAssociative(
            'SELECT id, property_group_option_id
             FROM product_configurator_setting
             WHERE product_id = :parentId
             AND product_version_id = :versionId',
            [
                'parentId' => Uuid::fromHexToBytes($parentId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        static::assertCount(2, $configSettingsBefore, 'Should have 2 configurator settings before deletion');

        $productRepository = static::getContainer()->get('product.repository');
        $productRepository->delete([['id' => $variant1Id]], $this->context);

        $configSettingsAfter = $this->connection->fetchAllAssociative(
            'SELECT id, property_group_option_id
             FROM product_configurator_setting
             WHERE product_id = :parentId
             AND product_version_id = :versionId',
            [
                'parentId' => Uuid::fromHexToBytes($parentId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        static::assertCount(1, $configSettingsAfter, 'Should have 1 configurator setting after deleting variant');
        static::assertSame(
            Uuid::fromHexToBytes($option2Id),
            $configSettingsAfter[0]['property_group_option_id'],
            'Remaining configurator setting should be for option 2'
        );
    }

    public function testBeforeDeleteProductKeepsConfiguratorSettingsWhenOtherVariantsStillUseOption(): void
    {
        $ids = new IdsCollection();

        $propertyGroupId = $ids->create('property-group');
        $option1Id = $ids->create('option-1');
        $option2Id = $ids->create('option-2');

        static::getContainer()->get('property_group.repository')->create([
            [
                'id' => $propertyGroupId,
                'name' => 'Color',
                'options' => [
                    ['id' => $option1Id, 'name' => 'Red'],
                    ['id' => $option2Id, 'name' => 'Blue'],
                ],
            ],
        ], $this->context);

        $parentId = $ids->create('parent');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $parentId,
                'name' => 'Parent Product',
                'productNumber' => 'PARENT-001',
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'configuratorSettings' => [
                    [
                        'id' => $ids->create('config-setting-1'),
                        'optionId' => $option1Id,
                    ],
                    [
                        'id' => $ids->create('config-setting-2'),
                        'optionId' => $option2Id,
                    ],
                ],
            ],
        ], $this->context);

        // Create variant 1 with option 1
        $variant1Id = $ids->create('variant-1');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $variant1Id,
                'parentId' => $parentId,
                'name' => 'Variant 1',
                'productNumber' => 'VAR-001',
                'stock' => 5,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'options' => [
                    ['id' => $option1Id],
                ],
            ],
        ], $this->context);

        // Create variant 2 with option 1 (same option as variant 1)
        $variant2Id = $ids->create('variant-2');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $variant2Id,
                'parentId' => $parentId,
                'name' => 'Variant 2',
                'productNumber' => 'VAR-002',
                'stock' => 5,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'options' => [
                    ['id' => $option1Id],
                ],
            ],
        ], $this->context);

        // Create variant 3 with option 2
        $variant3Id = $ids->create('variant-3');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $variant3Id,
                'parentId' => $parentId,
                'name' => 'Variant 3',
                'productNumber' => 'VAR-003',
                'stock' => 5,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'options' => [
                    ['id' => $option2Id],
                ],
            ],
        ], $this->context);

        $configSettingsBefore = $this->connection->fetchAllAssociative(
            'SELECT id, property_group_option_id
             FROM product_configurator_setting
             WHERE product_id = :parentId
             AND product_version_id = :versionId',
            [
                'parentId' => Uuid::fromHexToBytes($parentId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        static::assertCount(2, $configSettingsBefore, 'Should have 2 configurator settings before deletion');

        $productRepository = static::getContainer()->get('product.repository');
        $productRepository->delete([['id' => $variant1Id]], $this->context);

        // Verify both configurator settings still exist (both options still have variants)
        $configSettingsAfter = $this->connection->fetchAllAssociative(
            'SELECT id, property_group_option_id
             FROM product_configurator_setting
             WHERE product_id = :parentId
             AND product_version_id = :versionId',
            [
                'parentId' => Uuid::fromHexToBytes($parentId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        static::assertCount(2, $configSettingsAfter, 'Should still have 2 configurator settings after deleting variant');
    }

    public function testBeforeDeleteProductHandlesMultipleDeletedVariants(): void
    {
        $ids = new IdsCollection();

        $propertyGroupId = $ids->create('property-group');
        $option1Id = $ids->create('option-1');
        $option2Id = $ids->create('option-2');
        $option3Id = $ids->create('option-3');

        static::getContainer()->get('property_group.repository')->create([
            [
                'id' => $propertyGroupId,
                'name' => 'Color',
                'options' => [
                    ['id' => $option1Id, 'name' => 'Red'],
                    ['id' => $option2Id, 'name' => 'Blue'],
                    ['id' => $option3Id, 'name' => 'Green'],
                ],
            ],
        ], $this->context);

        $parentId = $ids->create('parent');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $parentId,
                'name' => 'Parent Product',
                'productNumber' => 'PARENT-001',
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'configuratorSettings' => [
                    [
                        'id' => $ids->create('config-setting-1'),
                        'optionId' => $option1Id,
                    ],
                    [
                        'id' => $ids->create('config-setting-2'),
                        'optionId' => $option2Id,
                    ],
                    [
                        'id' => $ids->create('config-setting-3'),
                        'optionId' => $option3Id,
                    ],
                ],
            ],
        ], $this->context);

        $variant1Id = $ids->create('variant-1');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $variant1Id,
                'parentId' => $parentId,
                'name' => 'Variant 1',
                'productNumber' => 'VAR-001',
                'stock' => 5,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'options' => [['id' => $option1Id]],
            ],
        ], $this->context);

        $variant2Id = $ids->create('variant-2');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $variant2Id,
                'parentId' => $parentId,
                'name' => 'Variant 2',
                'productNumber' => 'VAR-002',
                'stock' => 5,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'options' => [['id' => $option2Id]],
            ],
        ], $this->context);

        $variant3Id = $ids->create('variant-3');
        static::getContainer()->get('product.repository')->create([
            [
                'id' => $variant3Id,
                'parentId' => $parentId,
                'name' => 'Variant 3',
                'productNumber' => 'VAR-003',
                'stock' => 5,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 84, 'linked' => false],
                ],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'options' => [['id' => $option3Id]],
            ],
        ], $this->context);

        $configSettingsBefore = $this->connection->fetchAllAssociative(
            'SELECT id, property_group_option_id
             FROM product_configurator_setting
             WHERE product_id = :parentId
             AND product_version_id = :versionId',
            [
                'parentId' => Uuid::fromHexToBytes($parentId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        static::assertCount(3, $configSettingsBefore, 'Should have 3 configurator settings before deletion');

        $productRepository = static::getContainer()->get('product.repository');
        $productRepository->delete([['id' => $variant1Id], ['id' => $variant2Id]], $this->context);

        $configSettingsAfter = $this->connection->fetchAllAssociative(
            'SELECT id, property_group_option_id
             FROM product_configurator_setting
             WHERE product_id = :parentId
             AND product_version_id = :versionId',
            [
                'parentId' => Uuid::fromHexToBytes($parentId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        static::assertCount(1, $configSettingsAfter, 'Should have 1 configurator setting after deleting variants');
        static::assertSame(
            Uuid::fromHexToBytes($option3Id),
            $configSettingsAfter[0]['property_group_option_id'],
            'Remaining configurator setting should be for option 3'
        );
    }
}
