<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1763316536ChangeProductManufacturerLink;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(Migration1763316536ChangeProductManufacturerLink::class)]
class Migration1763316536ChangeProductManufacturerLinkTest extends TestCase
{
    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->ids = new IdsCollection();

        try {
            $this->connection->executeStatement(
                'DELETE FROM `product_manufacturer`'
            );
        } catch (\Throwable) {
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM `product_manufacturer`'
            );
        } catch (\Throwable) {
        }
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1763316536ChangeProductManufacturerLink();
        static::assertSame(1763316536, $migration->getCreationTimestamp());
    }

    public function testMigrationUpdate(): void
    {
        // rollback
        if (!$this->existLinkColumn('product_manufacturer')) {
            $this->connection->executeStatement(
                'ALTER TABLE `product_manufacturer` ADD COLUMN `link` VARCHAR(255) DEFAULT NULL;'
            );
        }
        if ($this->existLinkColumn('product_manufacturer_translation')) {
            $this->connection->executeStatement('ALTER TABLE `product_manufacturer_translation` DROP COLUMN `link`;');
        }
        static::assertTrue($this->existLinkColumn('product_manufacturer'));
        static::assertFalse($this->existLinkColumn('product_manufacturer_translation'));

        // prepare products

        $this->createProductManufacturer('M1', 'link1');
        $this->createProductManufacturer('M2', 'link2');
        $this->createProductManufacturer('M3', null);

        $expected = [
            [
                'id' => $this->ids->get('M1'),
                'name' => 'M1',
                'link' => 'link1',
            ],
            [
                'id' => $this->ids->get('M2'),
                'name' => 'M2',
                'link' => 'link2',
            ],
            [
                'id' => $this->ids->get('M3'),
                'name' => 'M3',
                'link' => null,
            ],
        ];

        static::assertSame(
            $expected,
            $this->connection->fetchAllAssociative(
                <<<'SQL'

SELECT LOWER(HEX(pm.id)) AS id, pmt.name AS name, pm.link AS link
FROM product_manufacturer pm
LEFT JOIN product_manufacturer_translation pmt ON pm.id = pmt.product_manufacturer_id AND pm.version_id = pmt.product_manufacturer_version_id
ORDER BY pmt.name ASC

SQL
            )
        );

        // test
        $migration = new Migration1763316536ChangeProductManufacturerLink();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(
            $expected,
            $this->connection->fetchAllAssociative(
                <<<'SQL'

SELECT LOWER(HEX(product_manufacturer_id)) AS id, name, link
FROM product_manufacturer_translation
ORDER BY name ASC

SQL
            )
        );

        static::assertTrue($this->existLinkColumn('product_manufacturer_translation'));
    }

    public function testMigrationUpdateDestructive(): void
    {
        // rollback
        if (!$this->existLinkColumn('product_manufacturer')) {
            $this->connection->executeStatement(
                'ALTER TABLE `product_manufacturer` ADD COLUMN `link` VARCHAR(255) DEFAULT NULL;'
            );
        }
        static::assertTrue($this->existLinkColumn('product_manufacturer'));

        // test
        $migration = new Migration1763316536ChangeProductManufacturerLink();
        $migration->updateDestructive($this->connection);

        static::assertFalse($this->existLinkColumn('product_manufacturer'));
    }

    private function existLinkColumn(string $table): bool
    {
        $existingColumns = $this->connection->createSchemaManager()->listTableColumns($table);

        return \array_key_exists('link', $existingColumns);
    }

    private function createProductManufacturer(string $name, ?string $link): void
    {
        $id = $this->ids->getBytes($name);
        $versionBinId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $languageBinId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $manufacturer = [
            'id' => $id,
            'version_id' => $versionBinId,
            'link' => $link,
            'created_at' => $now,
        ];
        $manufacturerTranslation = [
            'product_manufacturer_id' => $id,
            'product_manufacturer_version_id' => $versionBinId,
            'language_id' => $languageBinId,
            'name' => $name,
            'created_at' => $now,
        ];

        $this->connection->insert('product_manufacturer', $manufacturer);
        $this->connection->insert('product_manufacturer_translation', $manufacturerTranslation);
    }
}
