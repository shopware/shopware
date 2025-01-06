<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_6\Migration1731576063UpdateProductComparisonTemplate;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1731576063UpdateProductComparisonTemplate::class)]
class Migration1731576063UpdateProductComparisonTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1731576063UpdateProductComparisonTemplate();
        static::assertSame(1731576063, $migration->getCreationTimestamp());
    }

    public function testMigrationOverridesTemplates(): void
    {
        $fixturePath = __DIR__ . '/../../../../src/Core/Migration/Fixtures/productComparison-export-profiles/next-39314/';
        $templateOld = file_get_contents($fixturePath . 'google_old.xml.twig');
        $templateExpected = file_get_contents($fixturePath . 'google_new.xml.twig');

        static::assertNotFalse($templateOld);
        static::assertNotFalse($templateExpected);

        $affectedTemplateId = $this->prepareOldDatabaseEntry($templateOld);

        $migration = new Migration1731576063UpdateProductComparisonTemplate();
        $migration->update($this->connection);

        $currentEntry = $this->getCurrentBodyAndUpdateTimestamp($affectedTemplateId);

        static::assertNotFalse($currentEntry);
        static::assertSame($templateExpected, $currentEntry['body']);
        static::assertNotNull($currentEntry['updatedAt']);
    }

    /**
     * @return array<string, string>|false
     */
    private function getCurrentBodyAndUpdateTimestamp(string $id): array|false
    {
        $getProductExportSQL = <<<'SQL'
            SELECT body_template AS body, updated_at AS updatedAt
            FROM product_export
            WHERE id = ?
        SQL;

        return $this->connection->fetchAssociative($getProductExportSQL, [$id]);
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
