<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1792998292AddProductDocumentMediaFolder;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1792998292AddProductDocumentMediaFolder::class)]
class Migration1792998292AddProductDocumentMediaFolderTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1792998292AddProductDocumentMediaFolder $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1792998292AddProductDocumentMediaFolder();
        $this->removeFolder();
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

    public function testCreatesTheMissingFolderOfAnExistingDefaultFolder(): void
    {
        // an earlier run stopped after the default folder
        $defaultFolderId = Uuid::randomBytes();
        $this->connection->insert('media_default_folder', [
            'id' => $defaultFolderId,
            'entity' => 'product_document',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->migration->update($this->connection);

        static::assertSame(
            ['Product documents'],
            $this->connection->fetchFirstColumn('SELECT `name` FROM `media_folder` WHERE `default_folder_id` = :id', ['id' => $defaultFolderId])
        );
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
