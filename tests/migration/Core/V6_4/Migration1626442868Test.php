<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1626442868AddGermanSalesChannelDescription;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1626442868AddGermanSalesChannelDescription
 */
class Migration1626442868Test extends TestCase
{
    use MigrationTestTrait;

    final public const SALES_CHANNEL_STOREFRONT = 'Storefront';
    final public const SALES_CHANNEL_API = 'Headless';
    final public const BEFORE_MIGRATION_DESCRIPTION_STOREFRONT = 'Sales channel mit HTML storefront';
    final public const BEFORE_MIGRATION_DESCRIPTION_API = 'API only sales channel';
    final public const AFTER_MIGRATION_DESCRIPTION_STOREFRONT = 'Verkaufskanal mit HTML-Storefront';
    final public const AFTER_MIGRATION_DESCRIPTION_API = 'Verkaufskanal mit API-only-Zugang';
    final public const CUSTOM_DESCRIPTION = 'Custom Description';

    private string $oldDescriptionStorefront;

    private string $oldDescriptionAPI;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->oldDescriptionStorefront = $this->getDescription(self::SALES_CHANNEL_STOREFRONT);
        $this->oldDescriptionAPI = $this->getDescription(self::SALES_CHANNEL_API);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->resetDescription();
    }

    public function setDescription(string $salesChannel, string $description): void
    {
        $connection = $this->connection;
        $connection->executeStatement('
            UPDATE `sales_channel_type_translation`
            INNER JOIN `language`
            ON `language`.id = sales_channel_type_translation.language_id
            SET sales_channel_type_translation.description = ?
            WHERE `language`.name = "Deutsch"
            AND sales_channel_type_translation.name = ?
            AND sales_channel_type_translation.manufacturer = "shopware AG"
        ', [$description, $salesChannel]);
    }

    public function resetDescription(): void
    {
        $this->setDescription(self::SALES_CHANNEL_STOREFRONT, $this->oldDescriptionStorefront);
        $this->setDescription(self::SALES_CHANNEL_API, $this->oldDescriptionAPI);
    }

    public function setDescriptionToExpectedOne(): void
    {
        $this->setDescription(self::SALES_CHANNEL_STOREFRONT, self::BEFORE_MIGRATION_DESCRIPTION_STOREFRONT);
        $this->setDescription(self::SALES_CHANNEL_API, self::BEFORE_MIGRATION_DESCRIPTION_API);
    }

    public function getDescription(string $saleschannel): string
    {
        $connection = $this->connection;
        $result = $connection->fetchFirstColumn('
        SELECT description FROM sales_channel_type_translation
            INNER JOIN `language`
            ON `language`.id = sales_channel_type_translation.language_id
            WHERE `language`.name = "Deutsch"
            AND sales_channel_type_translation.name = ?
        ', [$saleschannel]);

        return $result[0];
    }

    public function testCustomDataNotChanged(): void
    {
        $this->setDescription(self::SALES_CHANNEL_STOREFRONT, self::CUSTOM_DESCRIPTION);
        $this->setDescription(self::SALES_CHANNEL_API, self::CUSTOM_DESCRIPTION);

        $migration = new Migration1626442868AddGermanSalesChannelDescription();
        $migration->update($this->connection);

        $newDescriptionStorefront = $this->getDescription(self::SALES_CHANNEL_STOREFRONT);
        $newDescriptionAPI = $this->getDescription(self::SALES_CHANNEL_API);

        static::assertEquals(self::CUSTOM_DESCRIPTION, $newDescriptionStorefront);
        static::assertEquals(self::CUSTOM_DESCRIPTION, $newDescriptionAPI);
    }

    public function testMigration(): void
    {
        $this->setDescriptionToExpectedOne();
        $migration = new Migration1626442868AddGermanSalesChannelDescription();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $newDescriptionStorefront = $this->getDescription(self::SALES_CHANNEL_STOREFRONT);
        $newDescriptionAPI = $this->getDescription(self::SALES_CHANNEL_API);

        static::assertEquals(self::AFTER_MIGRATION_DESCRIPTION_STOREFRONT, $newDescriptionStorefront);
        static::assertEquals(self::AFTER_MIGRATION_DESCRIPTION_API, $newDescriptionAPI);
    }
}
