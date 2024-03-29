<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1673946817FixMediaFolderAssociationFields;
use Shopware\Core\Migration\V6_6\Migration1679581138RemoveAssociationFields;

/**
 * @package core
 *
 * @internal
 */
#[CoversClass(Migration1673946817FixMediaFolderAssociationFields::class)]
class Migration1673946817FixMediaFolderAssociationFieldsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1673946817FixMediaFolderAssociationFields();
        static::assertEquals('1673946817', $migration->getCreationTimestamp());
    }

    public function testFieldsAreMigrated(): void
    {
        if (!$this->columnExists($this->connection, 'media_default_folder', 'association_fields')) {
            $this->connection->executeStatement('ALTER TABLE `media_default_folder` ADD COLUMN `association_fields` JSON NOT NULL;');
        }

        $this->connection->executeStatement('UPDATE media_default_folder SET association_fields = :association_fields WHERE entity = :user', [
            'association_fields' => '["avatarUser"]',
            'user' => 'user',
        ]);

        $migration = new Migration1673946817FixMediaFolderAssociationFields();
        $migration->update($this->connection);

        $fields = $this->connection->fetchOne('SELECT association_fields FROM media_default_folder WHERE entity = :user', ['user' => 'user']);

        static::assertJson($fields);
        $fields = \json_decode($fields, true);

        static::assertIsArray($fields);
        static::assertContains('avatarUsers', $fields);

        $migration = new Migration1679581138RemoveAssociationFields();
        $migration->updateDestructive($this->connection);
    }

    private function columnExists(Connection $connection, string $table, string $column): bool
    {
        $exists = $connection->fetchOne(
            'SHOW COLUMNS FROM `' . $table . '` WHERE `Field` LIKE :column',
            ['column' => $column]
        );

        return !empty($exists);
    }
}
