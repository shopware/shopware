<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779783880AddProductDocument;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779783880AddProductDocument::class)]
class Migration1779783880AddProductDocumentTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779783880, (new Migration1779783880AddProductDocument())->getCreationTimestamp());
    }

    public function testMigrationCreatesProductDocumentTableAndMediaFolder(): void
    {
        $this->rollback();

        $migration = new Migration1779783880AddProductDocument();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'product_document'));

        foreach (['id', 'version_id', 'product_id', 'product_version_id', 'media_id', 'position', 'created_at', 'updated_at'] as $column) {
            static::assertTrue(TableHelper::columnExists($this->connection, 'product_document', $column), \sprintf('Column %s is missing', $column));
        }

        static::assertSame(['id', 'version_id'], $this->getIndexColumns('product_document', 'PRIMARY'));
        static::assertSame(['product_id', 'product_version_id', 'media_id'], $this->getIndexColumns('product_document', 'uniq.product_document.product_media'));

        static::assertSame('CASCADE', $this->getDeleteRule('fk.product_document.product_id'));
        static::assertSame('RESTRICT', $this->getDeleteRule('fk.product_document.media_id'));

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'productDocuments'));

        $folder = $this->connection->fetchAssociative('
            SELECT
                HEX(`media_default_folder`.`id`) as `defaultFolderId`,
                `media_default_folder`.`entity`,
                `media_folder`.`name`,
                `media_folder_configuration`.`private`,
                `media_folder_configuration`.`create_thumbnails`
            FROM `media_default_folder`
            INNER JOIN `media_folder` ON `media_folder`.`default_folder_id` = `media_default_folder`.`id`
            INNER JOIN `media_folder_configuration` ON `media_folder_configuration`.`id` = `media_folder`.`media_folder_configuration_id`
            WHERE `media_default_folder`.`entity` = :entity
        ', ['entity' => 'product_document']);

        static::assertIsArray($folder);
        static::assertSame('product_document', $folder['entity']);
        static::assertSame('Product documents', $folder['name']);
        static::assertSame(1, (int) $folder['private']);
        static::assertSame(0, (int) $folder['create_thumbnails']);
    }

    /**
     * @return list<string>
     */
    private function getIndexColumns(string $table, string $indexName): array
    {
        /** @var list<array{COLUMN_NAME: string}> $rows */
        $rows = $this->connection->fetchAllAssociative('
            SELECT `COLUMN_NAME`
            FROM `information_schema`.`STATISTICS`
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_NAME` = :tableName
              AND `INDEX_NAME` = :indexName
            ORDER BY `SEQ_IN_INDEX`
        ', [
            'tableName' => $table,
            'indexName' => $indexName,
        ]);

        return array_column($rows, 'COLUMN_NAME');
    }

    private function getDeleteRule(string $constraintName): string
    {
        $deleteRule = $this->connection->fetchOne('
            SELECT `DELETE_RULE`
            FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
            WHERE `CONSTRAINT_SCHEMA` = DATABASE()
              AND `TABLE_NAME` = :tableName
              AND `CONSTRAINT_NAME` = :constraintName
        ', [
            'tableName' => 'product_document',
            'constraintName' => $constraintName,
        ]);

        static::assertIsString($deleteRule);

        return $deleteRule;
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `product_document`');

        if (TableHelper::columnExists($this->connection, 'product', 'productDocuments')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `productDocuments`');
        }

        $defaultFolderId = $this->connection->fetchOne('SELECT `id` FROM `media_default_folder` WHERE `entity` = :entity', ['entity' => 'product_document']);
        if (!$defaultFolderId) {
            return;
        }

        $folder = $this->connection->fetchAssociative('
            SELECT `id`, `media_folder_configuration_id`
            FROM `media_folder`
            WHERE `default_folder_id` = :defaultFolderId
        ', ['defaultFolderId' => $defaultFolderId]);

        if (\is_array($folder)) {
            $this->connection->delete('media_folder', ['id' => $folder['id']]);

            if ($folder['media_folder_configuration_id'] !== null) {
                $this->connection->delete('media_folder_configuration', ['id' => $folder['media_folder_configuration_id']]);
            }
        }

        $this->connection->delete('media_default_folder', ['id' => $defaultFolderId]);
    }
}
