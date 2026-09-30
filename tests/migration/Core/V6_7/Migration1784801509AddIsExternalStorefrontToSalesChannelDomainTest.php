<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1784801509AddIsExternalStorefrontToSalesChannelDomain;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1784801509AddIsExternalStorefrontToSalesChannelDomain::class)]
class Migration1784801509AddIsExternalStorefrontToSalesChannelDomainTest extends TestCase
{
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var list<string>
     */
    private array $createdSalesChannelIds = [];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    protected function tearDown(): void
    {
        if ($this->createdSalesChannelIds === []) {
            return;
        }

        // cascade also removes the domains that were created for these sales channels
        $this->connection->executeStatement(
            'DELETE FROM `sales_channel` WHERE `id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($this->createdSalesChannelIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $this->createdSalesChannelIds = [];
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1784801509,
            (new Migration1784801509AddIsExternalStorefrontToSalesChannelDomain())->getCreationTimestamp()
        );
    }

    public function testMigrate(): void
    {
        $this->rollback();

        // idempotent
        $this->migrate();
        $this->migrate();

        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_domain', 'is_external_storefront'));

        $column = TableHelper::getColumnOfTable($this->connection, 'sales_channel_domain', 'is_external_storefront');
        static::assertTrue($column->isNotNull);
        static::assertSame('0', (string) $column->defaultValue);

        static::assertTrue(TableHelper::columnExists($this->connection, 'sales_channel_domain', 'external_storefront_language_id'));

        static::assertTrue(TableHelper::indexExists($this->connection, 'sales_channel_domain', 'uniq.sales_channel_domain.external_storefront'));
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'sales_channel_domain',
            'uniq.sales_channel_domain.external_storefront',
            ['external_storefront_language_id', 'sales_channel_id']
        ));
    }

    public function testExternalStorefrontDomainsForDifferentSalesChannelsWithSameLanguageAreAllowed(): void
    {
        $this->migrate();

        $firstSalesChannelId = $this->createTestSalesChannel();
        $secondSalesChannelId = $this->createTestSalesChannel();

        $this->insertDomain($firstSalesChannelId, Defaults::LANGUAGE_SYSTEM, true);
        $this->insertDomain($secondSalesChannelId, Defaults::LANGUAGE_SYSTEM, true);

        static::assertSame(1, $this->countExternalStorefrontDomains($firstSalesChannelId));
        static::assertSame(1, $this->countExternalStorefrontDomains($secondSalesChannelId));
    }

    public function testExternalStorefrontDomainsForSameSalesChannelWithDifferentLanguagesAreAllowed(): void
    {
        $this->migrate();

        $salesChannelId = $this->createTestSalesChannel();

        $this->insertDomain($salesChannelId, Defaults::LANGUAGE_SYSTEM, true);
        $this->insertDomain($salesChannelId, $this->getAdditionalLanguageId(), true);

        static::assertSame(2, $this->countExternalStorefrontDomains($salesChannelId));
    }

    public function testMultipleNonExternalStorefrontDomainsForSameSalesChannelAndLanguageAreAllowed(): void
    {
        $this->migrate();

        $salesChannelId = $this->createTestSalesChannel();

        // non external storefront domains map to NULL and are ignored by the unique index
        $this->insertDomain($salesChannelId, Defaults::LANGUAGE_SYSTEM, false);
        $this->insertDomain($salesChannelId, Defaults::LANGUAGE_SYSTEM, false);

        static::assertSame(0, $this->countExternalStorefrontDomains($salesChannelId));
    }

    public function testSecondExternalStorefrontDomainForSameSalesChannelAndLanguageIsRejected(): void
    {
        $this->migrate();

        $salesChannelId = $this->createTestSalesChannel();

        $this->insertDomain($salesChannelId, Defaults::LANGUAGE_SYSTEM, true);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insertDomain($salesChannelId, Defaults::LANGUAGE_SYSTEM, true);
    }

    private function createTestSalesChannel(): string
    {
        $salesChannel = $this->createSalesChannel([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'https://sales-channel-' . Uuid::randomHex() . '.example',
                ],
            ],
        ]);

        $this->createdSalesChannelIds[] = $salesChannel['id'];

        return $salesChannel['id'];
    }

    private function insertDomain(string $salesChannelId, string $languageId, bool $externalStorefront): void
    {
        $this->connection->insert('sales_channel_domain', [
            'id' => Uuid::randomBytes(),
            'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
            'language_id' => Uuid::fromHexToBytes($languageId),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'snippet_set_id' => Uuid::fromHexToBytes((string) $this->getSnippetSetIdForLocale('en-GB')),
            'url' => 'https://' . Uuid::randomHex() . '.example',
            'is_external_storefront' => $externalStorefront ? 1 : 0,
            'created_at' => '2024-01-01 00:00:00.000',
        ]);
    }

    private function countExternalStorefrontDomains(string $salesChannelId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `sales_channel_domain` WHERE `sales_channel_id` = :id AND `is_external_storefront` = 1',
            ['id' => Uuid::fromHexToBytes($salesChannelId)]
        );
    }

    private function getAdditionalLanguageId(): string
    {
        $languageId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `language` WHERE `id` != :systemLanguageId LIMIT 1',
            ['systemLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        static::assertIsString($languageId);

        return $languageId;
    }

    private function migrate(): void
    {
        (new Migration1784801509AddIsExternalStorefrontToSalesChannelDomain())->update($this->connection);
    }

    private function rollback(): void
    {
        // drop in dependency order and guard each drop independently so a half-migrated state is handled:
        // the unique index first, then the generated column that depends on `is_external_storefront`, then the flag
        if (TableHelper::indexExists($this->connection, 'sales_channel_domain', 'uniq.sales_channel_domain.external_storefront')) {
            $this->connection->executeStatement('ALTER TABLE `sales_channel_domain` DROP INDEX `uniq.sales_channel_domain.external_storefront`');
        }

        if (TableHelper::columnExists($this->connection, 'sales_channel_domain', 'external_storefront_language_id')) {
            $this->connection->executeStatement('ALTER TABLE `sales_channel_domain` DROP COLUMN `external_storefront_language_id`');
        }

        if (TableHelper::columnExists($this->connection, 'sales_channel_domain', 'is_external_storefront')) {
            $this->connection->executeStatement('ALTER TABLE `sales_channel_domain` DROP COLUMN `is_external_storefront`');
        }
    }
}
