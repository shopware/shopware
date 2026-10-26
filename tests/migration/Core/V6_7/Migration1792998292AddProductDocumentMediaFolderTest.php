<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1792998292AddProductDocumentMediaFolder;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1792998292AddProductDocumentMediaFolder::class)]
class Migration1792998292AddProductDocumentMediaFolderTest extends TestCase
{
    private Connection $connection;

    private Migration1792998292AddProductDocumentMediaFolder $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1792998292AddProductDocumentMediaFolder();
        $this->removeFolder();
    }

    #[After]
    public function restore(): void
    {
        // the remaining test suite expects the migrated folder
        $this->migration->update($this->connection);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792998292, $this->migration->getCreationTimestamp());
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

    public function testQueuesTheMediaFolderIndexers(): void
    {
        $queuer = new IndexerQueuer($this->connection);
        $queuer->finishIndexer(['media_folder.indexer', 'media_folder_configuration.indexer']);

        $this->migration->update($this->connection);

        $queued = $queuer->getIndexers();
        static::assertArrayHasKey('media_folder.indexer', $queued);
        static::assertArrayHasKey('media_folder_configuration.indexer', $queued);
    }

    private function removeFolder(): void
    {
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
