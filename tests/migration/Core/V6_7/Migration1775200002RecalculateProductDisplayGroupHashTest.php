<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1775200001IncreaseProductDisplayGroupLength;
use Shopware\Core\Migration\V6_7\Migration1775200002RecalculateProductDisplayGroupHash;

/**
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
        $this->cleanup([$variantAId, $variantBId, $parentId]);

        $parentIdHex = strtolower(bin2hex($parentId));
        $legacyHash = md5($parentIdHex);

        $this->connection->insert('product', [
            'id' => $parentId,
            'version_id' => $liveVersion,
            'parent_id' => null,
            'parent_version_id' => null,
            'product_number' => 'migration-parent',
            'stock' => 10,
            'display_group' => md5($parentIdHex),
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

        (new Migration1775200001IncreaseProductDisplayGroupLength())->update($this->connection);

        $migration = new Migration1775200002RecalculateProductDisplayGroupHash();
        static::assertSame(1775200002, $migration->getCreationTimestamp());

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

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

    private function cleanupByProductNumbers(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM product WHERE product_number IN (:productNumbers)',
            ['productNumbers' => ['migration-parent', 'migration-variant-a', 'migration-variant-b']],
            ['productNumbers' => ArrayParameterType::STRING]
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
