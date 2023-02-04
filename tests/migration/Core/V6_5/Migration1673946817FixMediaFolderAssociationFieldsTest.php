<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1673946817FixMediaFolderAssociationFields;

/**
 * @package core
 *
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1673946817FixMediaFolderAssociationFields
 */
class Migration1673946817FixMediaFolderAssociationFieldsTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
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
        $migration = new Migration1673946817FixMediaFolderAssociationFields();
        $migration->update($this->connection);

        $fields = $this->connection->fetchOne('SELECT association_fields FROM media_default_folder WHERE entity = :user', ['user' => 'user']);

        static::assertJson($fields);
        $fields = \json_decode($fields, true);

        static::assertIsArray($fields);
        static::assertContains('avatarUsers', $fields);
    }
}
