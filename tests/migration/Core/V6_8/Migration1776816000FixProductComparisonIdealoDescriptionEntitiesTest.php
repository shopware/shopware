<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1776816000FixProductComparisonIdealoDescriptionEntities;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(Migration1776816000FixProductComparisonIdealoDescriptionEntities::class)]
class Migration1776816000FixProductComparisonIdealoDescriptionEntitiesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        static::assertSame(1776816000, (new Migration1776816000FixProductComparisonIdealoDescriptionEntities())->getCreationTimestamp());
    }

    /**
     * @param array{oldTemplate: string, expectedTemplate: string} $testData
     */
    #[DataProvider('dataProvider')]
    public function testMigration(array $testData): void
    {
        $currentEntryId = $this->prepareOldDatabaseEntry($testData['oldTemplate']);

        $migration = new Migration1776816000FixProductComparisonIdealoDescriptionEntities();
        $migration->update($this->connection);
        $migration->update($this->connection);

        /** @var array{body: string, updatedAt: string|null} $currentEntry */
        $currentEntry = $this->getCurrentBodyAndUpdateTimestamp($currentEntryId);

        static::assertSame($testData['expectedTemplate'], $currentEntry['body']);
        if ($testData['expectedTemplate'] === $testData['oldTemplate']) {
            static::assertNull($currentEntry['updatedAt']);
        }
    }

    /**
     * @return list<array{0: array{oldTemplate: string, expectedTemplate: string}}>
     */
    public static function dataProvider(): array
    {
        $fixturePath = __DIR__ . '/../../../../src/Core/Migration/Fixtures/productComparison-export-profiles/next-13664/';

        /** @var string $oldTemplate */
        $oldTemplate = file_get_contents($fixturePath . 'old-template-idealo.csv.twig');
        /** @var string $newTemplate */
        $newTemplate = file_get_contents($fixturePath . 'new-template-idealo.csv.twig');
        /** @var string $old37658Template */
        $old37658Template = file_get_contents($fixturePath . 'old-template-idealo-37658.csv.twig');
        /** @var string $new37658Template */
        $new37658Template = file_get_contents($fixturePath . 'new-template-idealo-37658.csv.twig');

        return [
            [['oldTemplate' => 'do_not_update_for_modified_template', 'expectedTemplate' => 'do_not_update_for_modified_template']],
            [['oldTemplate' => $oldTemplate, 'expectedTemplate' => $newTemplate]],
            [['oldTemplate' => $old37658Template, 'expectedTemplate' => $new37658Template]],
        ];
    }

    /**
     * @return array<string, mixed>|false
     */
    private function getCurrentBodyAndUpdateTimestamp(string $id): array|false
    {
        $sql = <<<'SQL'
            SELECT body_template AS body, updated_at AS updatedAt
            FROM product_export
            WHERE id = ?
        SQL;

        return $this->connection->fetchAssociative($sql, [$id]);
    }

    private function prepareOldDatabaseEntry(string $body): string
    {
        $id = Uuid::randomBytes();
        $productStreamId = Uuid::randomBytes();

        $this->connection->insert('product_stream', [
            'id' => $productStreamId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert(
            'product_export',
            [
                'id' => $id,
                'product_stream_id' => $productStreamId,
                'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                'file_name' => Uuid::randomHex(),
                'access_key' => 'SWPEM3BKR1DJWNHEYTJQQU84AQ',
                'encoding' => 'UTF-8',
                'file_format' => 'CSV',
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
