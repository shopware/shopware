<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1641289204FixProductComparisonGoogleShippingPriceDisplay;
use Shopware\Core\Test\TestDefaults;

class Migration1641289204FixProductComparisonGoogleShippingPriceDisplayTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private string $oldTemplate;

    private string $newTemplate;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->oldTemplate = file_get_contents(__DIR__ . '/../Fixtures/productComparison-export-profiles/next-19135/body_old.xml.twig');
        $this->newTemplate = file_get_contents(__DIR__ . '/../Fixtures/productComparison-export-profiles/next-19135/body_new.xml.twig');
    }

    /**
     * @dataProvider dataProvider
     */
    public function testMigration(array $testData): void
    {
        $currentEntryId = $this->prepareOldDatabaseEntry($testData['old_template']);

        $migration = new Migration1641289204FixProductComparisonGoogleShippingPriceDisplay();
        $migration->update($this->connection);

        $currentEntry = $this->getCurrentBodyAndUpdateTimestamp($currentEntryId);

        static::assertSame($testData['expectedTemplate'], $currentEntry['body']);
        if ($testData['expectedTemplate'] === $testData['old_template']) {
            static::assertNull($currentEntry['updatedAt']);
        }
    }

    public function testMigrationRunsTwice(): void
    {
        $currentEntryId = $this->prepareOldDatabaseEntry($this->oldTemplate);

        $migration = new Migration1641289204FixProductComparisonGoogleShippingPriceDisplay();
        $migration->update($this->connection);

        $entryAfterRun1 = $this->getCurrentBodyAndUpdateTimestamp($currentEntryId);

        $migration->update($this->connection);

        static::assertSame($this->newTemplate, $entryAfterRun1['body']);
    }

    public function dataProvider(): array
    {
        $old_template = file_get_contents(__DIR__ . '/../Fixtures/productComparison-export-profiles/next-19135/body_old.xml.twig');
        $new_template = file_get_contents(__DIR__ . '/../Fixtures/productComparison-export-profiles/next-19135/body_new.xml.twig');

        return [
            [['old_template' => 'testData', 'expectedTemplate' => 'testData']],
            [['old_template' => $old_template, 'expectedTemplate' => $new_template]],
        ];
    }

    private function getCurrentBodyAndUpdateTimestamp(string $id): array
    {
        $SQL = <<<'SQL'
            SELECT body_template AS body, updated_at AS updatedAt
            FROM product_export
            WHERE id = ?
        SQL;

        return $this->connection->fetchAssociative($SQL, [$id]);
    }

    private function prepareOldDatabaseEntry(string $body): string
    {
        $id = Uuid::randomBytes();
        $productStreamID = Uuid::randomBytes();

        $this->connection->insert('product_stream', [
            'id' => $productStreamID,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert(
            'product_export',
            [
                'id' => $id,
                'product_stream_id' => $productStreamID,
                'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                'file_name' => Uuid::randomHex(),
                'access_key' => 'SWPERXF3DUTXS0JGRWRWWDHMTA',
                'encoding' => 'UTF-8',
                'file_format' => 'test',
                '`interval`' => 300,
                'body_template' => $body,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            ],
            [
                'id' => Types::BINARY,
                'product_stream_id' => Types::BINARY,
                'sales_channel_id' => Types::BINARY,
                'currency_id' => Types::BINARY,
            ]
        );

        return $id;
    }
}
