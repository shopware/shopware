<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1618989442AddProductConfigurationSettingsUniqKey;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 * NEXT-21735 - Not deterministic due to SalesChannelContextFactory
 *
 * @group not-deterministic
 */
#[Package('core')]
class Migration1618989442AddProductConfigurationSettingsUniqKeyTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    private EntityRepository $productRepository;

    private SalesChannelRepository $salesChannelProductRepository;

    private SalesChannelContext $context;

    private ProductConfiguratorLoader $loader;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->salesChannelProductRepository = $this->getContainer()->get('sales_channel.product.repository');

        $this->context = $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create('test', TestDefaults::SALES_CHANNEL);

        $this->loader = $this->getContainer()->get(ProductConfiguratorLoader::class);

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigration(): void
    {
        $this->connection->rollBack();
        $this->revertMigration();

        $hasIndex = $this->hasIndex();

        static::assertFalse($hasIndex);

        $this->executeMigration();

        $hasIndex = $this->hasIndex();

        static::assertTrue($hasIndex);

        $this->connection->beginTransaction();
    }

    public function testMigrationRemovesExistingDuplicates(): void
    {
        $this->connection->rollBack();
        $this->revertMigration();

        $productConfiguratorSettingId1 = Uuid::randomHex();
        $productConfiguratorSettingId2 = Uuid::randomHex();
        $productConfiguratorSettingId3 = Uuid::randomHex();

        // disable fk check to prevent creating a lot of overhead data
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        // insert duplicates
        $this->connection->executeStatement('
            INSERT INTO `product_configurator_setting`
                (id, version_id, product_id, product_version_id, property_group_option_id, created_at)
            VALUES
                (:id1, :liveVersion, :productId, :liveVersion, :propertyGroupOptionId, :createdAt),
                (:id2, :liveVersion, :productId, :liveVersion, :propertyGroupOptionId, :createdAt),
                (:id3, :liveVersion, :productId, :liveVersion, :propertyGroupOptionId, :createdAt)
        ', [
            'id1' => Uuid::fromHexToBytes($productConfiguratorSettingId1),
            'id2' => Uuid::fromHexToBytes($productConfiguratorSettingId2),
            'id3' => Uuid::fromHexToBytes($productConfiguratorSettingId3),
            'liveVersion' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'productId' => Uuid::randomBytes(),
            'propertyGroupOptionId' => Uuid::randomBytes(),
            'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $this->executeMigration();

        $actualProductConfiguratorSettings = $this->getContainer()->get('product_configurator_setting.repository')
            ->search(new Criteria([$productConfiguratorSettingId1, $productConfiguratorSettingId2, $productConfiguratorSettingId3]), Context::createDefaultContext())
            ->getTotal();

        static::assertEquals(1, $actualProductConfiguratorSettings);

        // cleanup
        $this->connection->executeStatement('
            DELETE FROM `product_configurator_setting` WHERE id IN (:ids)
        ', [
            'ids' => [
                Uuid::fromHexToBytes($productConfiguratorSettingId1),
                Uuid::fromHexToBytes($productConfiguratorSettingId2),
                Uuid::fromHexToBytes($productConfiguratorSettingId3),
            ],
        ], [
            'ids' => ArrayParameterType::STRING,
        ]);

        $this->connection->beginTransaction();
    }

    public function testDuplicateProductConfiguratorSettingsSortedByUuid(): void
    {
        // drop unique index for testing purposes
        $this->connection->rollBack();
        $this->revertMigration();
        $this->connection->beginTransaction();

        $productId = $this->insertDuplicateData();

        $criteria = (new Criteria())->addFilter(new EqualsFilter('product.parentId', $productId));
        $salesChannelProduct = $this->salesChannelProductRepository->search($criteria, $this->context)->first();

        // property group with highest value UUID should be returned
        $propertyGroups = $this->loader->load($salesChannelProduct, $this->context);
        static::assertEquals(1, $propertyGroups->count());
        $propertyGroup = $propertyGroups->first();
        static::assertEquals(1, $propertyGroup->getOptions()->count());
        $option = $propertyGroup->getOptions()->first();
        static::assertSame('00000000000000000000000000000999', $option->getConfiguratorSetting()->getId());

        // remove duplicates, as the migration would
        $this->removeDuplicates();

        // property group with highest value UUID should be returned
        $propertyGroups = $this->loader->load($salesChannelProduct, $this->context);
        static::assertEquals(1, $propertyGroups->count());
        $propertyGroup = $propertyGroups->first();
        static::assertEquals(1, $propertyGroup->getOptions()->count());
        $option = $propertyGroup->getOptions()->first();
        static::assertSame('00000000000000000000000000000999', $option->getConfiguratorSetting()->getId());

        $this->connection->rollBack();
        $this->executeMigration();
        $this->connection->beginTransaction();
    }

    private function executeMigration(): void
    {
        (new Migration1618989442AddProductConfigurationSettingsUniqKey())->update($this->connection);
    }

    private function removeDuplicates(): void
    {
        // remove existing duplicates
        $this->connection->executeStatement('
            DELETE config1 FROM product_configurator_setting AS config1
            INNER JOIN product_configurator_setting AS config2
            WHERE config1.id < config2.id
                AND config1.product_id = config2.product_id
                AND config1.product_version_id = config2.product_version_id
                AND config1.property_group_option_id = config2.property_group_option_id;
        ');
    }

    private function revertMigration(): void
    {
        if ($this->hasIndex()) {
            $this->connection->executeStatement('
                ALTER TABLE `product_configurator_setting`
                DROP INDEX `uniq.product_configurator_setting.prod_id.vers_id.prop_group_id`
            ');
        }

        if ($this->hasNewIndex()) {
            $this->connection->executeStatement('
                ALTER TABLE `product_configurator_setting`
                DROP INDEX `uniq.product_configurator_setting.p_id.vers_id.prop_group_id.cS`
            ');
        }
    }

    private function hasIndex(): bool
    {
        return (bool) $this->connection->executeQuery('
            SHOW INDEXES IN `product_configurator_setting`
            WHERE `Key_name` = \'uniq.product_configurator_setting.prod_id.vers_id.prop_group_id\'
        ')->fetchOne();
    }

    private function hasNewIndex(): bool
    {
        return (bool) $this->connection->executeQuery('
            SHOW INDEXES IN `product_configurator_setting`
            WHERE `Key_name` = \'uniq.product_configurator_setting.p_id.vers_id.prop_group_id.cS\'
        ')->fetchOne();
    }

    private function insertDuplicateData(): string
    {
        $productId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $groupId = Uuid::randomHex();
        $optionId = Uuid::randomHex();

        $optionIds = [$groupId => [$optionId]];

        $configuratorSettings = [
            [
                'id' => '00000000000000000000000000000005',
                'position' => 1,
                'option' => [
                    'id' => $optionId,
                    'name' => 'red',
                    'group' => [
                        'id' => $groupId,
                        'name' => 'color',
                    ],
                ],
            ],
            [
                'id' => '00000000000000000000000000000999',
                'position' => 2,
                'option' => [
                    'id' => $optionId,
                    'name' => 'red',
                    'group' => [
                        'id' => $groupId,
                        'name' => 'color',
                    ],
                ],
            ],
            [
                'id' => '00000000000000000000000000000001',
                'position' => 4,
                'option' => [
                    'id' => $optionId,
                    'name' => 'red',
                    'group' => [
                        'id' => $groupId,
                        'name' => 'color',
                    ],
                ],
            ],
        ];

        $data = [
            [
                'id' => $productId,
                'name' => 'Test product',
                'productNumber' => 'a.0',
                'manufacturer' => ['name' => 'test'],
                'tax' => ['id' => UUid::randomHex(), 'taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'active' => true,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true]],
                'configuratorSettings' => $configuratorSettings,
                'configuratorGroupConfig' => null,
                'visibilities' => [
                    [
                        'salesChannelId' => TestDefaults::SALES_CHANNEL,
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $variantId,
                'productNumber' => 'variant',
                'stock' => 10,
                'active' => true,
                'parentId' => $productId,
                'options' => array_map(fn (array $group) => ['id' => $group[0]], $optionIds),
            ],
        ];

        $this->productRepository->create($data, Context::createDefaultContext());
        $this->addTaxDataToSalesChannel($this->context, $data[0]['tax']);

        return $productId;
    }
}
