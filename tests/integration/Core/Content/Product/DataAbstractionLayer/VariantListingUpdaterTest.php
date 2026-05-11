<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class VariantListingUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private VariantListingUpdater $updater;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->updater = new VariantListingUpdater($this->connection);
    }

    public function testUpdateAppliesSha256DisplayGroupForListingGroupConfiguration(): void
    {
        $liveVersion = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $createdAt = '2000-01-01 00:00:00.000';

        $groupHex = Uuid::randomHex();
        $groupBytes = Uuid::fromHexToBytes($groupHex);
        $optionRedHex = Uuid::randomHex();
        $optionGreenHex = Uuid::randomHex();
        $optionRedBytes = Uuid::fromHexToBytes($optionRedHex);
        $optionGreenBytes = Uuid::fromHexToBytes($optionGreenHex);

        $parentId = Uuid::randomBytes();
        $variantAId = Uuid::randomBytes();
        $variantBId = Uuid::randomBytes();

        $parentHex = strtolower(bin2hex($parentId));

        $this->cleanupVariantListingFixture(
            [$variantAId, $variantBId, $parentId],
            [$optionRedBytes, $optionGreenBytes],
            $groupBytes
        );

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
            'configuratorGroupConfig' => [[
                'id' => $groupHex,
                'expressionForListings' => true,
                'representation' => 'box',
            ]],
        ], \JSON_THROW_ON_ERROR);

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'vl-updater-parent',
            'stock' => 10,
            'variant_listing_config' => $listingConfig,
            'display_group' => null,
        ]);
        foreach ([[$variantAId, 'vl-updater-a'], [$variantBId, 'vl-updater-b']] as [$variantId, $number]) {
            $this->connection->insert('product', [
                'id' => $variantId,
                'version_id' => $liveVersion,
                'parent_id' => $parentId,
                'parent_version_id' => $liveVersion,
                'product_number' => $number,
                'stock' => 10,
                'display_group' => null,
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

        $this->updater->update([$parentHex], Context::createDefaultContext());

        $rows = $this->connection->fetchAllAssociative(
            'SELECT product_number, display_group FROM product WHERE id IN (:ids) AND version_id = :version ORDER BY product_number ASC',
            ['ids' => [$variantAId, $variantBId], 'version' => $liveVersion],
            ['ids' => ArrayParameterType::BINARY]
        );
        $displayByNumber = array_column($rows, 'display_group', 'product_number');

        $expectedRed = hash('sha256', $parentHex . strtolower(bin2hex($optionRedBytes)));
        $expectedGreen = hash('sha256', $parentHex . strtolower(bin2hex($optionGreenBytes)));

        static::assertSame($expectedRed, $displayByNumber['vl-updater-a']);
        static::assertSame($expectedGreen, $displayByNumber['vl-updater-b']);

        $this->cleanupVariantListingFixture(
            [$variantAId, $variantBId, $parentId],
            [$optionRedBytes, $optionGreenBytes],
            $groupBytes
        );
    }

    /**
     * @param list<string> $productIds
     * @param list<string> $optionIds
     */
    private function cleanupVariantListingFixture(
        array $productIds,
        array $optionIds,
        string $groupBytes
    ): void {
        $this->connection->executeStatement(
            'DELETE FROM product_option WHERE property_group_option_id IN (:ids)',
            ['ids' => $optionIds],
            ['ids' => ArrayParameterType::BINARY]
        );
        foreach ($productIds as $id) {
            $this->connection->executeStatement('DELETE FROM product WHERE id = :id', ['id' => $id]);
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
}
