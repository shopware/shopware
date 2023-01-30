<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1635230747UpdateProductExportTemplate;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1635230747UpdateProductExportTemplate
 */
class Migration1635230747UpdateProductExportTemplateTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @param array{old_template: string, expectedTemplate: string} $testData
     *
     * @dataProvider dataProvider
     */
    public function testMigrationOverridesTemplatesWithLeadingSpaces(array $testData): void
    {
        $currentEntryId = $this->prepareOldDatabaseEntry($testData['old_template']);

        $migration = new Migration1635230747UpdateProductExportTemplate();
        $migration->update($this->connection);

        $currentEntry = $this->getCurrentBodyAndUpdateTimestamp($currentEntryId);

        static::assertSame($testData['expectedTemplate'], $currentEntry['body']);
        if ($testData['expectedTemplate'] === $testData['old_template']) {
            static::assertNull($currentEntry['updatedAt']);
        }
    }

    /**
     * @return array{old_template: string, expectedTemplate: string}[][]
     */
    public function dataProvider(): array
    {
        /** @var array<string, string> $templates */
        $templates = require __DIR__ . '/../../../../src/Core/Migration/Fixtures/productComparison-export-profiles/templates.php';

        return [
            [['old_template' => 'testData', 'expectedTemplate' => 'testData']],
            [['old_template' => $templates['billiger_new'], 'expectedTemplate' => $templates['billiger_new']]],
            [['old_template' => $templates['idealo_new'], 'expectedTemplate' => $templates['idealo_new']]],
            [['old_template' => $templates['google_new'], 'expectedTemplate' => $templates['google_new']]],
            [['old_template' => $templates['billiger_old'], 'expectedTemplate' => $templates['billiger_new']]],
            [['old_template' => $templates['idealo_old'], 'expectedTemplate' => $templates['idealo_new']]],
            [['old_template' => $templates['google_old'], 'expectedTemplate' => $templates['google_new']]],
        ];
    }

    /**
     * @return array{body: string, updatedAt: string|null}
     */
    private function getCurrentBodyAndUpdateTimestamp(string $id): array
    {
        $getProductExportSQL = '
            SELECT body_template AS body, updated_at AS updatedAt
            FROM product_export
            WHERE id = ?
        ';

        /** @var array{body: string, updatedAt: string|null} $result */
        $result = $this->connection->fetchAssociative($getProductExportSQL, [$id]);

        return $result;
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
                'id' => 'binary',
                'product_stream_id' => 'binary',
                'sales_channel_id' => 'binary',
                'currency_id' => 'binary',
            ]
        );

        return $id;
    }
}
