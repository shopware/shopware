<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1775200002RecalculateProductDisplayGroupHash;

/**
 * Assumes a fully migrated test database (including {@see \Shopware\Core\Migration\V6_7\Migration1775200001IncreaseProductDisplayGroupLength}
 * so `product.display_group` is wide enough for SHA-256 hex values). Same as a normal installation after upgrade.
 *
 * Several scenarios assert the same outcome: parent `display_group` NULL, all variants share
 * `hash('sha256', strtoupper(parentIdHex))`. That is still meaningful: each test uses a **different**
 * `variant_listing_config` (display parent, main variant, invalid JSON, bad group id, etc.). They all collapse to
 * no listing dimensions in {@see \Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater}, so the
 * single-variant listing branch runs. The value is proving decoding and edge-case handling, not a different hash shape.
 * Tests that wire real listing dimensions and `product_option` rows assert **distinct** variant hashes instead.
 *
 * @internal
 */
#[CoversClass(Migration1775200002RecalculateProductDisplayGroupHash::class)]
class Migration1775200002RecalculateProductDisplayGroupHashTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationRecalculatesDisplayGroupWithSha256(): void
    {
        $parentId = Uuid::randomBytes();
        $variantAId = Uuid::randomBytes();
        $variantBId = Uuid::randomBytes();
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->cleanupByProductNumbers();

        $parentIdHex = strtolower(bin2hex($parentId));
        $legacyHash = md5($parentIdHex);

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'migration-parent',
            'stock' => 10,
            'display_group' => $legacyHash,
        ]);

        $this->connection->insert('product', [
            'id' => $variantAId,
            'version_id' => $liveVersion,
            'parent_id' => $parentId,
            'parent_version_id' => $liveVersion,
            'product_number' => 'migration-variant-a',
            'stock' => 10,
            'display_group' => $legacyHash,
        ]);

        $this->connection->insert('product', [
            'id' => $variantBId,
            'version_id' => $liveVersion,
            'parent_id' => $parentId,
            'parent_version_id' => $liveVersion,
            'product_number' => 'migration-variant-b',
            'stock' => 10,
            'display_group' => $legacyHash,
        ]);

        $migration = new Migration1775200002RecalculateProductDisplayGroupHash();
        static::assertSame(1775200002, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);
        $this->runScheduledVariantListingIndexer();

        $parentDisplayGroup = $this->connection->fetchOne(
            'SELECT display_group FROM product WHERE id = :id AND version_id = :versionId',
            ['id' => $parentId, 'versionId' => $liveVersion]
        );

        static::assertNull($parentDisplayGroup);

        $variantDisplayGroups = $this->connection->fetchFirstColumn(
            'SELECT display_group FROM product WHERE id IN (:ids) AND version_id = :versionId ORDER BY product_number ASC',
            ['ids' => [$variantAId, $variantBId], 'versionId' => $liveVersion],
            ['ids' => ArrayParameterType::BINARY]
        );

        $expectedHash = hash('sha256', strtoupper($parentIdHex));

        static::assertCount(2, $variantDisplayGroups);
        static::assertSame([$expectedHash, $expectedHash], $variantDisplayGroups);

        $this->cleanup([$variantAId, $variantBId, $parentId]);
    }

    public function testMigrationRecalculatesDisplayGroupForConfiguratorListingGroups(): void
    {
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $createdAt = '2000-01-01 00:00:00.000';

        $groupHex = Uuid::randomHex();
        $groupBytes = Uuid::fromHexToBytes($groupHex);
        $optionRedBytes = Uuid::fromHexToBytes(Uuid::randomHex());
        $optionGreenBytes = Uuid::fromHexToBytes(Uuid::randomHex());

        $parentId = Uuid::randomBytes();
        $variantAId = Uuid::randomBytes();
        $variantBId = Uuid::randomBytes();
        $parentHex = strtolower(bin2hex($parentId));

        $this->cleanupListingGroupRows($groupBytes, [$optionRedBytes, $optionGreenBytes], [$variantAId, $variantBId, $parentId]);
        $this->cleanupByProductNumbers();

        $this->connection->insert('property_group', [
            'id' => $groupBytes,
            'created_at' => $createdAt,
        ]);
        $this->connection->insert('property_group_translation', [
            'property_group_id' => $groupBytes,
            'language_id' => $languageId,
            'name' => 'Color',
            'created_at' => $createdAt,
        ]);
        foreach ([[$optionRedBytes, 'Red'], [$optionGreenBytes, 'Green']] as [$optionBytes, $name]) {
            $this->connection->insert('property_group_option', [
                'id' => $optionBytes,
                'property_group_id' => $groupBytes,
                'created_at' => $createdAt,
            ]);
            $this->connection->insert('property_group_option_translation', [
                'property_group_option_id' => $optionBytes,
                'language_id' => $languageId,
                'name' => $name,
                'created_at' => $createdAt,
            ]);
        }

        $listingConfig = json_encode([
            'displayParent' => null,
            'mainVariantId' => null,
            'configuratorGroupConfig' => [
                ['unexpected' => true],
                [
                    'id' => $groupHex,
                    'expressionForListings' => true,
                    'representation' => 'box',
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'migration-dg-group-parent',
            'stock' => 10,
            'variant_listing_config' => $listingConfig,
            'display_group' => md5($parentHex),
        ]);
        foreach ([[$variantAId, 'migration-dg-group-a'], [$variantBId, 'migration-dg-group-b']] as [$variantId, $number]) {
            $this->connection->insert('product', [
                'id' => $variantId,
                'version_id' => $liveVersion,
                'parent_id' => $parentId,
                'parent_version_id' => $liveVersion,
                'product_number' => $number,
                'stock' => 10,
                'display_group' => md5($parentHex),
            ]);
        }

        $this->connection->insert('product_option', [
            'product_id' => $variantAId,
            'product_version_id' => $liveVersion,
            'property_group_option_id' => $optionRedBytes,
        ]);
        $this->connection->insert('product_option', [
            'product_id' => $variantBId,
            'product_version_id' => $liveVersion,
            'property_group_option_id' => $optionGreenBytes,
        ]);

        (new Migration1775200002RecalculateProductDisplayGroupHash())->update($this->connection);
        $this->runScheduledVariantListingIndexer();

        $parentDisplayGroup = $this->connection->fetchOne(
            'SELECT display_group FROM product WHERE id = :id AND version_id = :versionId',
            ['id' => $parentId, 'versionId' => $liveVersion]
        );
        static::assertNull($parentDisplayGroup);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT product_number, display_group FROM product WHERE id IN (:ids) AND version_id = :version ORDER BY product_number ASC',
            ['ids' => [$variantAId, $variantBId], 'version' => $liveVersion],
            ['ids' => ArrayParameterType::BINARY]
        );
        $displayByNumber = array_column($rows, 'display_group', 'product_number');

        static::assertSame(
            hash('sha256', $parentHex . strtolower(bin2hex($optionRedBytes))),
            $displayByNumber['migration-dg-group-a']
        );
        static::assertSame(
            hash('sha256', $parentHex . strtolower(bin2hex($optionGreenBytes))),
            $displayByNumber['migration-dg-group-b']
        );

        $this->cleanupListingGroupRows($groupBytes, [$optionRedBytes, $optionGreenBytes], [$variantAId, $variantBId, $parentId]);
    }

    public function testMigrationCompletesWhenVariantListingConfigDecodesToNonArray(): void
    {
        $parentId = Uuid::randomBytes();
        $variantAId = Uuid::randomBytes();
        $variantBId = Uuid::randomBytes();
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->cleanupByProductNumbers();

        $parentIdHex = strtolower(bin2hex($parentId));
        $legacyHash = md5($parentIdHex);

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'migration-dg-scalar-parent',
            'stock' => 10,
            'variant_listing_config' => '42',
            'display_group' => $legacyHash,
        ]);

        foreach ([[$variantAId, 'migration-dg-scalar-a'], [$variantBId, 'migration-dg-scalar-b']] as [$variantId, $number]) {
            $this->connection->insert('product', [
                'id' => $variantId,
                'version_id' => $liveVersion,
                'parent_id' => $parentId,
                'parent_version_id' => $liveVersion,
                'product_number' => $number,
                'stock' => 10,
                'display_group' => $legacyHash,
            ]);
        }

        (new Migration1775200002RecalculateProductDisplayGroupHash())->update($this->connection);
        $this->runScheduledVariantListingIndexer();

        $this->assertParentDisplayGroupNullWhenParentHasVariants($parentId, $liveVersion);

        $expectedHash = hash('sha256', strtoupper($parentIdHex));
        $variantDisplayGroups = $this->connection->fetchFirstColumn(
            'SELECT display_group FROM product WHERE id IN (:ids) AND version_id = :versionId ORDER BY product_number ASC',
            ['ids' => [$variantAId, $variantBId], 'versionId' => $liveVersion],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertSame([$expectedHash, $expectedHash], $variantDisplayGroups);

        $this->cleanup([$variantAId, $variantBId, $parentId]);
    }

    public function testMigrationSetsDisplayGroupForParentProductWithoutVariants(): void
    {
        $parentId = Uuid::randomBytes();
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->cleanupByProductNumbers();

        $parentIdHex = strtolower(bin2hex($parentId));

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'migration-dg-solo-parent',
            'stock' => 10,
            'display_group' => md5($parentIdHex),
        ]);

        (new Migration1775200002RecalculateProductDisplayGroupHash())->update($this->connection);
        $this->runScheduledVariantListingIndexer();

        $displayGroup = $this->connection->fetchOne(
            'SELECT display_group FROM product WHERE id = :id AND version_id = :versionId',
            ['id' => $parentId, 'versionId' => $liveVersion]
        );

        static::assertSame(hash('sha256', strtoupper($parentIdHex)), $displayGroup);

        $this->cleanup([$parentId]);
    }

    public function testMigrationClearsListingGroupsWhenDisplayParentIsEnabled(): void
    {
        $parentId = Uuid::randomBytes();
        $variantAId = Uuid::randomBytes();
        $variantBId = Uuid::randomBytes();
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->cleanupByProductNumbers();

        $parentIdHex = strtolower(bin2hex($parentId));
        $legacyHash = md5($parentIdHex);

        $listingConfig = json_encode([
            'displayParent' => true,
            'mainVariantId' => null,
            'configuratorGroupConfig' => [[
                'id' => Uuid::randomHex(),
                'expressionForListings' => true,
            ]],
        ], \JSON_THROW_ON_ERROR);

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'migration-dg-disparent-parent',
            'stock' => 10,
            'variant_listing_config' => $listingConfig,
            'display_group' => $legacyHash,
        ]);

        foreach ([[$variantAId, 'migration-dg-disparent-a'], [$variantBId, 'migration-dg-disparent-b']] as [$variantId, $number]) {
            $this->connection->insert('product', [
                'id' => $variantId,
                'version_id' => $liveVersion,
                'parent_id' => $parentId,
                'parent_version_id' => $liveVersion,
                'product_number' => $number,
                'stock' => 10,
                'display_group' => $legacyHash,
            ]);
        }

        (new Migration1775200002RecalculateProductDisplayGroupHash())->update($this->connection);
        $this->runScheduledVariantListingIndexer();

        $this->assertParentDisplayGroupNullWhenParentHasVariants($parentId, $liveVersion);

        $expectedHash = hash('sha256', strtoupper($parentIdHex));
        $variantDisplayGroups = $this->connection->fetchFirstColumn(
            'SELECT display_group FROM product WHERE id IN (:ids) AND version_id = :versionId ORDER BY product_number ASC',
            ['ids' => [$variantAId, $variantBId], 'versionId' => $liveVersion],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertSame([$expectedHash, $expectedHash], $variantDisplayGroups);

        $this->cleanup([$variantAId, $variantBId, $parentId]);
    }

    public function testMigrationClearsListingGroupsWhenMainVariantIdIsSet(): void
    {
        $parentId = Uuid::randomBytes();
        $variantAId = Uuid::randomBytes();
        $variantBId = Uuid::randomBytes();
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->cleanupByProductNumbers();

        $parentIdHex = strtolower(bin2hex($parentId));
        $legacyHash = md5($parentIdHex);
        $variantAHex = Uuid::fromBytesToHex($variantAId);

        $listingConfig = json_encode([
            'displayParent' => null,
            'mainVariantId' => $variantAHex,
            'configuratorGroupConfig' => [[
                'id' => Uuid::randomHex(),
                'expressionForListings' => true,
            ]],
        ], \JSON_THROW_ON_ERROR);

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'migration-dg-mainvar-parent',
            'stock' => 10,
            'variant_listing_config' => $listingConfig,
            'display_group' => $legacyHash,
        ]);

        foreach ([[$variantAId, 'migration-dg-mainvar-a'], [$variantBId, 'migration-dg-mainvar-b']] as [$variantId, $number]) {
            $this->connection->insert('product', [
                'id' => $variantId,
                'version_id' => $liveVersion,
                'parent_id' => $parentId,
                'parent_version_id' => $liveVersion,
                'product_number' => $number,
                'stock' => 10,
                'display_group' => $legacyHash,
            ]);
        }

        (new Migration1775200002RecalculateProductDisplayGroupHash())->update($this->connection);
        $this->runScheduledVariantListingIndexer();

        $this->assertParentDisplayGroupNullWhenParentHasVariants($parentId, $liveVersion);

        $expectedHash = hash('sha256', strtoupper($parentIdHex));
        $variantDisplayGroups = $this->connection->fetchFirstColumn(
            'SELECT display_group FROM product WHERE id IN (:ids) AND version_id = :versionId ORDER BY product_number ASC',
            ['ids' => [$variantAId, $variantBId], 'versionId' => $liveVersion],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertSame([$expectedHash, $expectedHash], $variantDisplayGroups);

        $this->cleanup([$variantAId, $variantBId, $parentId]);
    }

    public function testMigrationTreatsNonArrayConfiguratorGroupConfigAsEmpty(): void
    {
        $parentId = Uuid::randomBytes();
        $variantAId = Uuid::randomBytes();
        $variantBId = Uuid::randomBytes();
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->cleanupByProductNumbers();

        $parentIdHex = strtolower(bin2hex($parentId));
        $legacyHash = md5($parentIdHex);

        $listingConfig = json_encode([
            'displayParent' => null,
            'mainVariantId' => null,
            'configuratorGroupConfig' => 'not-an-array',
        ], \JSON_THROW_ON_ERROR);

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'migration-dg-cgc-parent',
            'stock' => 10,
            'variant_listing_config' => $listingConfig,
            'display_group' => $legacyHash,
        ]);

        foreach ([[$variantAId, 'migration-dg-cgc-a'], [$variantBId, 'migration-dg-cgc-b']] as [$variantId, $number]) {
            $this->connection->insert('product', [
                'id' => $variantId,
                'version_id' => $liveVersion,
                'parent_id' => $parentId,
                'parent_version_id' => $liveVersion,
                'product_number' => $number,
                'stock' => 10,
                'display_group' => $legacyHash,
            ]);
        }

        (new Migration1775200002RecalculateProductDisplayGroupHash())->update($this->connection);
        $this->runScheduledVariantListingIndexer();

        $this->assertParentDisplayGroupNullWhenParentHasVariants($parentId, $liveVersion);

        $expectedHash = hash('sha256', strtoupper($parentIdHex));
        $variantDisplayGroups = $this->connection->fetchFirstColumn(
            'SELECT display_group FROM product WHERE id IN (:ids) AND version_id = :versionId ORDER BY product_number ASC',
            ['ids' => [$variantAId, $variantBId], 'versionId' => $liveVersion],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertSame([$expectedHash, $expectedHash], $variantDisplayGroups);

        $this->cleanup([$variantAId, $variantBId, $parentId]);
    }

    public function testMigrationSkipsNonUuidConfiguratorGroupId(): void
    {
        $parentId = Uuid::randomBytes();
        $variantAId = Uuid::randomBytes();
        $variantBId = Uuid::randomBytes();
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $this->cleanupByProductNumbers();

        $parentIdHex = strtolower(bin2hex($parentId));
        $legacyHash = md5($parentIdHex);

        $listingConfig = json_encode([
            'displayParent' => null,
            'mainVariantId' => null,
            'configuratorGroupConfig' => [[
                'id' => 'not-a-valid-32-char-hex-uuid-string',
                'expressionForListings' => true,
            ]],
        ], \JSON_THROW_ON_ERROR);

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'migration-dg-baduuid-parent',
            'stock' => 10,
            'variant_listing_config' => $listingConfig,
            'display_group' => $legacyHash,
        ]);

        foreach ([[$variantAId, 'migration-dg-baduuid-a'], [$variantBId, 'migration-dg-baduuid-b']] as [$variantId, $number]) {
            $this->connection->insert('product', [
                'id' => $variantId,
                'version_id' => $liveVersion,
                'parent_id' => $parentId,
                'parent_version_id' => $liveVersion,
                'product_number' => $number,
                'stock' => 10,
                'display_group' => $legacyHash,
            ]);
        }

        (new Migration1775200002RecalculateProductDisplayGroupHash())->update($this->connection);
        $this->runScheduledVariantListingIndexer();

        $this->assertParentDisplayGroupNullWhenParentHasVariants($parentId, $liveVersion);

        $expectedHash = hash('sha256', strtoupper($parentIdHex));
        $variantDisplayGroups = $this->connection->fetchFirstColumn(
            'SELECT display_group FROM product WHERE id IN (:ids) AND version_id = :versionId ORDER BY product_number ASC',
            ['ids' => [$variantAId, $variantBId], 'versionId' => $liveVersion],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertSame([$expectedHash, $expectedHash], $variantDisplayGroups);

        $this->cleanup([$variantAId, $variantBId, $parentId]);
    }

    public function testMigrationKeepsValidConfiguratorGroupWhenMixedWithInvalidGroupId(): void
    {
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $createdAt = '2000-01-01 00:00:00.000';

        $groupHex = Uuid::randomHex();
        $groupBytes = Uuid::fromHexToBytes($groupHex);
        $optionRedBytes = Uuid::fromHexToBytes(Uuid::randomHex());
        $optionGreenBytes = Uuid::fromHexToBytes(Uuid::randomHex());

        $parentId = Uuid::randomBytes();
        $variantAId = Uuid::randomBytes();
        $variantBId = Uuid::randomBytes();
        $parentHex = strtolower(bin2hex($parentId));

        $this->cleanupListingGroupRows($groupBytes, [$optionRedBytes, $optionGreenBytes], [$variantAId, $variantBId, $parentId]);
        $this->cleanupByProductNumbers();

        $this->connection->insert('property_group', [
            'id' => $groupBytes,
            'created_at' => $createdAt,
        ]);
        $this->connection->insert('property_group_translation', [
            'property_group_id' => $groupBytes,
            'language_id' => $languageId,
            'name' => 'Color',
            'created_at' => $createdAt,
        ]);
        foreach ([[$optionRedBytes, 'Red'], [$optionGreenBytes, 'Green']] as [$optionBytes, $name]) {
            $this->connection->insert('property_group_option', [
                'id' => $optionBytes,
                'property_group_id' => $groupBytes,
                'created_at' => $createdAt,
            ]);
            $this->connection->insert('property_group_option_translation', [
                'property_group_option_id' => $optionBytes,
                'language_id' => $languageId,
                'name' => $name,
                'created_at' => $createdAt,
            ]);
        }

        $listingConfig = json_encode([
            'displayParent' => null,
            'mainVariantId' => null,
            'configuratorGroupConfig' => [
                [
                    'id' => 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz',
                    'expressionForListings' => true,
                ],
                [
                    'id' => $groupHex,
                    'expressionForListings' => true,
                    'representation' => 'box',
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'migration-dg-mixuuid-parent',
            'stock' => 10,
            'variant_listing_config' => $listingConfig,
            'display_group' => md5($parentHex),
        ]);
        foreach ([[$variantAId, 'migration-dg-mixuuid-a'], [$variantBId, 'migration-dg-mixuuid-b']] as [$variantId, $number]) {
            $this->connection->insert('product', [
                'id' => $variantId,
                'version_id' => $liveVersion,
                'parent_id' => $parentId,
                'parent_version_id' => $liveVersion,
                'product_number' => $number,
                'stock' => 10,
                'display_group' => md5($parentHex),
            ]);
        }

        $this->connection->insert('product_option', [
            'product_id' => $variantAId,
            'product_version_id' => $liveVersion,
            'property_group_option_id' => $optionRedBytes,
        ]);
        $this->connection->insert('product_option', [
            'product_id' => $variantBId,
            'product_version_id' => $liveVersion,
            'property_group_option_id' => $optionGreenBytes,
        ]);

        (new Migration1775200002RecalculateProductDisplayGroupHash())->update($this->connection);
        $this->runScheduledVariantListingIndexer();

        $this->assertParentDisplayGroupNullWhenParentHasVariants($parentId, $liveVersion);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT product_number, display_group FROM product WHERE id IN (:ids) AND version_id = :version ORDER BY product_number ASC',
            ['ids' => [$variantAId, $variantBId], 'version' => $liveVersion],
            ['ids' => ArrayParameterType::BINARY]
        );
        $displayByNumber = array_column($rows, 'display_group', 'product_number');

        static::assertSame(
            hash('sha256', $parentHex . strtolower(bin2hex($optionRedBytes))),
            $displayByNumber['migration-dg-mixuuid-a']
        );
        static::assertSame(
            hash('sha256', $parentHex . strtolower(bin2hex($optionGreenBytes))),
            $displayByNumber['migration-dg-mixuuid-b']
        );

        $this->cleanupListingGroupRows($groupBytes, [$optionRedBytes, $optionGreenBytes], [$variantAId, $variantBId, $parentId]);
    }

    /**
     * If a parent has children, {@see \Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater} runs
     * `hideParent` and sets the parent `display_group` to NULL. How the storefront listing behaves (show parent only,
     * prefer a main variant, or split rows by listing dimensions) is driven by `variant_listing_config` and reflected in
     * **variant** `display_group` values, not by storing a listing hash on the parent while variants exist.
     *
     * @param string $parentId binary product id
     * @param string $liveVersion binary version id
     */
    private function assertParentDisplayGroupNullWhenParentHasVariants(string $parentId, string $liveVersion): void
    {
        $parentDisplayGroup = $this->connection->fetchOne(
            'SELECT display_group FROM product WHERE id = :id AND version_id = :versionId',
            ['id' => $parentId, 'versionId' => $liveVersion]
        );
        static::assertNull($parentDisplayGroup);
    }

    /**
     * Same product index subset as {@see Migration1775200002RecalculateProductDisplayGroupHash} schedules via
     * {@see \Shopware\Core\Framework\Migration\IndexerQueuer} (variant listing / display_group only).
     */
    private function runScheduledVariantListingIndexer(): void
    {
        $registry = KernelLifecycleManager::getKernel()->getContainer()->get(EntityIndexerRegistry::class);
        $productIndexer = $registry->getIndexer('product.indexer');
        static::assertNotNull($productIndexer);

        $skipList = \array_values(\array_diff($productIndexer->getOptions(), [ProductIndexer::VARIANT_LISTING_UPDATER]));
        $registry->index(false, $skipList, ['product.indexer']);
    }

    private function cleanupByProductNumbers(): void
    {
        // Prefix `migration-dg-` covers all scenario fixtures; legacy names predate that convention.
        $this->connection->executeStatement(
            'DELETE FROM product WHERE product_number LIKE :pattern OR product_number IN (:legacy)',
            [
                'pattern' => 'migration-dg-%',
                'legacy' => ['migration-parent', 'migration-variant-a', 'migration-variant-b'],
            ],
            ['legacy' => ArrayParameterType::STRING]
        );
    }

    /**
     * @param list<string> $optionIds
     * @param list<string> $productIds
     */
    private function cleanupListingGroupRows(string $groupBytes, array $optionIds, array $productIds): void
    {
        $this->connection->executeStatement(
            'DELETE FROM product_option WHERE property_group_option_id IN (:ids)',
            ['ids' => $optionIds],
            ['ids' => ArrayParameterType::BINARY]
        );
        foreach ($productIds as $id) {
            $this->connection->executeStatement(
                'DELETE FROM product WHERE id = :id',
                ['id' => $id]
            );
        }
        foreach ($optionIds as $optionId) {
            $this->connection->executeStatement(
                'DELETE FROM property_group_option_translation WHERE property_group_option_id = :id',
                ['id' => $optionId]
            );
            $this->connection->executeStatement(
                'DELETE FROM property_group_option WHERE id = :id',
                ['id' => $optionId]
            );
        }
        $this->connection->executeStatement(
            'DELETE FROM property_group_translation WHERE property_group_id = :id',
            ['id' => $groupBytes]
        );
        $this->connection->executeStatement(
            'DELETE FROM property_group WHERE id = :id',
            ['id' => $groupBytes]
        );
    }

    /**
     * @param list<string> $ids
     */
    private function cleanup(array $ids): void
    {
        foreach ($ids as $id) {
            $this->connection->executeStatement(
                'DELETE FROM product WHERE id = :id',
                ['id' => $id]
            );
        }
    }
}
