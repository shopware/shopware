<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1780645634AddProductDescriptionTeaser;

/**
 * @internal
 */
#[CoversClass(Migration1780645634AddProductDescriptionTeaser::class)]
class Migration1780645634AddProductDescriptionTeaserTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1780645634, (new Migration1780645634AddProductDescriptionTeaser())->getCreationTimestamp());
    }

    public function testUpdateAddsPlainColumn(): void
    {
        $this->dropTeaserColumnIfExists();

        $migration = new Migration1780645634AddProductDescriptionTeaser();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = $this->connection->fetchAssociative(
            'SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'product_translation\' AND COLUMN_NAME = \'description_teaser\''
        );

        static::assertIsArray($column);
        static::assertSame('varchar', $column['DATA_TYPE']);
        static::assertSame(512, (int) $column['CHARACTER_MAXIMUM_LENGTH']);
        static::assertStringNotContainsStringIgnoringCase('generated', (string) $column['EXTRA']);
    }

    public function testUpdateBackfillsStrippedDescription(): void
    {
        $this->dropTeaserColumnIfExists();

        $languageId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM language LIMIT 1');
        static::assertIsString($languageId);

        $productId = Uuid::randomHex();
        $versionId = Defaults::LIVE_VERSION;

        $this->connection->insert('product', [
            'id' => Uuid::fromHexToBytes($productId),
            'version_id' => Uuid::fromHexToBytes($versionId),
            'product_number' => 'teaser-backfill-' . $productId,
            'stock' => 1,
            'created_at' => '2026-01-01 00:00:00.000',
        ]);
        $this->connection->insert('product_translation', [
            'product_id' => Uuid::fromHexToBytes($productId),
            'product_version_id' => Uuid::fromHexToBytes($versionId),
            'language_id' => Uuid::fromHexToBytes($languageId),
            'description' => '<p style="color:red">Hello <strong>World</strong></p>',
            'created_at' => '2026-01-01 00:00:00.000',
        ]);

        try {
            $migration = new Migration1780645634AddProductDescriptionTeaser();
            $migration->update($this->connection);

            $teaser = $this->connection->fetchOne(
                'SELECT description_teaser FROM product_translation WHERE product_id = :id',
                ['id' => Uuid::fromHexToBytes($productId)]
            );

            static::assertSame('Hello World', $teaser);
        } finally {
            $this->connection->delete('product', ['id' => Uuid::fromHexToBytes($productId)]);
        }
    }

    private function dropTeaserColumnIfExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'product_translation', 'description_teaser')) {
            $this->connection->executeStatement('ALTER TABLE `product_translation` DROP COLUMN `description_teaser`');
        }
    }
}
