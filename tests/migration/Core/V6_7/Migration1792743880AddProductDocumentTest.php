<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1792743880AddProductDocument;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1792743880AddProductDocument::class)]
class Migration1792743880AddProductDocumentTest extends TestCase
{
    private Connection $connection;

    private Migration1792743880AddProductDocument $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1792743880AddProductDocument();
        $this->rollback();
    }

    #[After]
    public function restore(): void
    {
        // the remaining test suite needs the table
        $this->migration->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792743880, $this->migration->getCreationTimestamp());
    }

    public function testCreatesTheProductDocumentTable(): void
    {
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertSame(
            ['id', 'version_id', 'product_id', 'product_version_id', 'language_id', 'media_id', 'title', 'position', 'custom_fields', 'created_at', 'updated_at'],
            $this->connection->fetchFirstColumn(
                'SELECT `COLUMN_NAME` FROM `information_schema`.`COLUMNS`
                 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table
                 ORDER BY `ORDINAL_POSITION`',
                ['table' => 'product_document']
            )
        );
        static::assertSame(
            ['product_id', 'product_version_id', 'language_id', 'media_id'],
            $this->connection->fetchFirstColumn(
                'SELECT `COLUMN_NAME` FROM `information_schema`.`STATISTICS`
                 WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table AND `INDEX_NAME` = :index
                 ORDER BY `SEQ_IN_INDEX`',
                ['table' => 'product_document', 'index' => 'uniq.product_document.product_language_media']
            )
        );
        static::assertSame(
            [
                'fk.product_document.language_id' => 'CASCADE',
                'fk.product_document.media_id' => 'CASCADE',
                'fk.product_document.product_id' => 'CASCADE',
            ],
            $this->connection->fetchAllKeyValue(
                'SELECT `CONSTRAINT_NAME`, `DELETE_RULE` FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
                 WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = :table
                 ORDER BY `CONSTRAINT_NAME`',
                ['table' => 'product_document']
            )
        );
    }

    public function testAddsTheInheritanceColumnToProducts(): void
    {
        $this->migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'documents'));
    }

    public function testCreatesAPublicDefaultFolderWithoutThumbnails(): void
    {
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $folders = $this->connection->fetchAllAssociative(
            'SELECT `media_folder`.`name`, `media_folder`.`use_parent_configuration`, `media_folder_configuration`.`private`, `media_folder_configuration`.`create_thumbnails`
             FROM `media_default_folder`
             INNER JOIN `media_folder` ON `media_folder`.`default_folder_id` = `media_default_folder`.`id`
             INNER JOIN `media_folder_configuration` ON `media_folder_configuration`.`id` = `media_folder`.`media_folder_configuration_id`
             WHERE `media_default_folder`.`entity` = :entity',
            ['entity' => 'product_document']
        );

        static::assertSame([[
            'name' => 'Product documents',
            'use_parent_configuration' => '0',
            'private' => '0',
            'create_thumbnails' => '0',
        ]], $folders);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `product_document`');

        if (TableHelper::columnExists($this->connection, 'product', 'documents')) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `documents`');
        }

        $defaultFolderId = $this->connection->fetchOne('SELECT `id` FROM `media_default_folder` WHERE `entity` = :entity', ['entity' => 'product_document']);
        if ($defaultFolderId === false) {
            return;
        }

        $configurationIds = $this->connection->fetchFirstColumn(
            'SELECT `media_folder_configuration_id` FROM `media_folder` WHERE `default_folder_id` = :id',
            ['id' => $defaultFolderId]
        );
        $this->connection->delete('media_folder', ['default_folder_id' => $defaultFolderId]);
        foreach ($configurationIds as $configurationId) {
            $this->connection->delete('media_folder_configuration', ['id' => $configurationId]);
        }
        $this->connection->delete('media_default_folder', ['id' => $defaultFolderId]);
    }
}
