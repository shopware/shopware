<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1786378835AddDocumentBaseConfigFilenameInfixes;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1786378835AddDocumentBaseConfigFilenameInfixes::class)]
class Migration1786378835AddDocumentBaseConfigFilenameInfixesTest extends TestCase
{
    private Connection $connection;

    /**
     * @var list<string>
     */
    private array $documentBaseConfigIds = [];

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        if ($this->documentBaseConfigIds === []) {
            return;
        }

        $this->connection->executeStatement(
            'DELETE FROM `document_base_config` WHERE `id` IN (:ids)',
            ['ids' => array_map(Uuid::fromHexToBytes(...), $this->documentBaseConfigIds)],
            ['ids' => ArrayParameterType::BINARY],
        );
    }

    public function testMigrationAddsNullableColumn(): void
    {
        $this->rollback();

        static::assertFalse($this->columnExists());

        $migration = new Migration1786378835AddDocumentBaseConfigFilenameInfixes();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists());

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName(DocumentBaseConfigDefinition::ENTITY_NAME)
            ->getColumn('filename_infixes');

        static::assertFalse($column->getNotnull());
    }

    public function testMigrationSeedsZugferdEmbeddedPdfInfixOnlyForTypesThatSupportIt(): void
    {
        $this->rollback();

        $invoiceTypeId = $this->fetchDocumentTypeId('invoice');
        $deliveryNoteTypeId = $this->fetchDocumentTypeId('delivery_note');

        $invoiceConfigId = Uuid::randomHex();
        $deliveryNoteConfigId = Uuid::randomHex();
        $this->documentBaseConfigIds = [$invoiceConfigId, $deliveryNoteConfigId];

        $this->insertDocumentBaseConfig($invoiceConfigId, $invoiceTypeId);
        $this->insertDocumentBaseConfig($deliveryNoteConfigId, $deliveryNoteTypeId);

        $migration = new Migration1786378835AddDocumentBaseConfigFilenameInfixes();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(
            '{"zugferd_embedded_pdf": "_zugferd"}',
            $this->fetchFilenameInfixes($invoiceConfigId),
        );
        static::assertNull($this->fetchFilenameInfixes($deliveryNoteConfigId));
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(
            1786378835,
            (new Migration1786378835AddDocumentBaseConfigFilenameInfixes())->getCreationTimestamp()
        );
    }

    private function fetchDocumentTypeId(string $technicalName): string
    {
        $documentTypeId = $this->connection->fetchOne(
            'SELECT `id` FROM `document_type` WHERE `technical_name` = :technicalName LIMIT 1',
            ['technicalName' => $technicalName],
        );
        static::assertIsString($documentTypeId);

        return $documentTypeId;
    }

    private function fetchFilenameInfixes(string $id): ?string
    {
        $value = $this->connection->fetchOne(
            'SELECT `filename_infixes` FROM `document_base_config` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($id)],
        );

        return \is_string($value) ? $value : null;
    }

    private function insertDocumentBaseConfig(string $id, string $documentTypeId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO `document_base_config`
                (`id`, `document_type_id`, `name`, `global`, `created_at`)
             VALUES
                (:id, :documentTypeId, :name, 0, NOW())',
            [
                'id' => Uuid::fromHexToBytes($id),
                'documentTypeId' => $documentTypeId,
                'name' => 'test-' . $id,
            ],
        );
    }

    private function columnExists(): bool
    {
        return TableHelper::columnExists($this->connection, DocumentBaseConfigDefinition::ENTITY_NAME, 'filename_infixes');
    }

    private function rollback(): void
    {
        if (!$this->columnExists()) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `document_base_config` DROP COLUMN `filename_infixes`');
    }
}
