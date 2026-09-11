<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1787229827MakeDocumentOrderOptional;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1787229827MakeDocumentOrderOptional::class)]
class Migration1787229827MakeDocumentOrderOptionalTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        (new Migration1787229827MakeDocumentOrderOptional())->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1787229827, (new Migration1787229827MakeDocumentOrderOptional())->getCreationTimestamp());
    }

    public function testMigrationMakesOrderReferenceNullableAndKeepsForeignKeyConstraint(): void
    {
        $this->prepareRequiredOrderReference();

        static::assertTrue(TableHelper::getColumnOfTable($this->connection, DocumentDefinition::ENTITY_NAME, 'order_id')->isNotNull);
        static::assertTrue(TableHelper::getColumnOfTable($this->connection, DocumentDefinition::ENTITY_NAME, 'order_version_id')->isNotNull);

        $migration = new Migration1787229827MakeDocumentOrderOptional();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertFalse(TableHelper::getColumnOfTable($this->connection, DocumentDefinition::ENTITY_NAME, 'order_id')->isNotNull);
        static::assertFalse(TableHelper::getColumnOfTable($this->connection, DocumentDefinition::ENTITY_NAME, 'order_version_id')->isNotNull);

        $foreignKey = TableHelper::getForeignKeyOfTable($this->connection, DocumentDefinition::ENTITY_NAME, 'fk.document.order_id');

        static::assertSame(['order_id', 'order_version_id'], $foreignKey->referencingColumnNames);
        static::assertSame('order', $foreignKey->referencedTableName);
        static::assertSame(['id', 'version_id'], $foreignKey->referencedColumnNames);
    }

    public function testDocumentCanBePersistedWithoutOrderReference(): void
    {
        $documentTypeId = Uuid::randomHex();
        $documentId = Uuid::randomHex();

        $migration = new Migration1787229827MakeDocumentOrderOptional();
        $migration->update($this->connection);

        $this->connection->insert('document_type', [
            'id' => Uuid::fromHexToBytes($documentTypeId),
            'technical_name' => 'test_document_without_order_' . $documentTypeId,
            'created_at' => $this->now(),
        ]);

        try {
            $document = [
                'id' => Uuid::fromHexToBytes($documentId),
                'document_type_id' => Uuid::fromHexToBytes($documentTypeId),
                'order_id' => null,
                'order_version_id' => null,
                'config' => '{}',
                'sent' => 0,
                'static' => 0,
                'deep_link_code' => Uuid::randomHex(),
                'created_at' => $this->now(),
            ];

            if (TableHelper::columnExists($this->connection, DocumentDefinition::ENTITY_NAME, 'file_type')) {
                $document['file_type'] = 'pdf';
            }

            $this->connection->insert(DocumentDefinition::ENTITY_NAME, $document);

            static::assertSame(1, (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `document` WHERE `id` = :id AND `order_id` IS NULL AND `order_version_id` IS NULL',
                ['id' => Uuid::fromHexToBytes($documentId)]
            ));
        } finally {
            $this->connection->delete(DocumentDefinition::ENTITY_NAME, ['id' => Uuid::fromHexToBytes($documentId)]);
            $this->connection->delete('document_type', ['id' => Uuid::fromHexToBytes($documentTypeId)]);
        }
    }

    public function testForeignKeyStillRejectsUnknownOrderReference(): void
    {
        $documentTypeId = Uuid::randomHex();

        $migration = new Migration1787229827MakeDocumentOrderOptional();
        $migration->update($this->connection);

        $this->connection->insert('document_type', [
            'id' => Uuid::fromHexToBytes($documentTypeId),
            'technical_name' => 'test_document_invalid_order_' . $documentTypeId,
            'created_at' => $this->now(),
        ]);

        try {
            $document = [
                'id' => Uuid::randomBytes(),
                'document_type_id' => Uuid::fromHexToBytes($documentTypeId),
                'order_id' => Uuid::randomBytes(),
                'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'config' => '{}',
                'sent' => 0,
                'static' => 0,
                'deep_link_code' => Uuid::randomHex(),
                'created_at' => $this->now(),
            ];

            if (TableHelper::columnExists($this->connection, DocumentDefinition::ENTITY_NAME, 'file_type')) {
                $document['file_type'] = 'pdf';
            }

            $this->expectException(ForeignKeyConstraintViolationException::class);

            $this->connection->insert(DocumentDefinition::ENTITY_NAME, $document);
        } finally {
            $this->connection->delete('document_type', ['id' => Uuid::fromHexToBytes($documentTypeId)]);
        }
    }

    private function prepareRequiredOrderReference(): void
    {
        static::assertSame(0, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `document` WHERE `order_id` IS NULL OR `order_version_id` IS NULL'
        ));

        if (TableHelper::foreignKeyExists($this->connection, DocumentDefinition::ENTITY_NAME, 'fk.document.order_id')) {
            $this->connection->executeStatement('ALTER TABLE `document` DROP FOREIGN KEY `fk.document.order_id`');
        }

        $this->connection->executeStatement('
            ALTER TABLE `document`
                MODIFY COLUMN `order_id` BINARY(16) NOT NULL,
                MODIFY COLUMN `order_version_id` BINARY(16) NOT NULL
        ');

        $this->connection->executeStatement('
            ALTER TABLE `document`
                ADD CONSTRAINT `fk.document.order_id`
                FOREIGN KEY (`order_id`, `order_version_id`)
                REFERENCES `order` (`id`, `version_id`)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ');
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
