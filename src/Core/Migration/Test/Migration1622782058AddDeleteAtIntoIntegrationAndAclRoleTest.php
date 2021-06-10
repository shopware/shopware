<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1622782058AddDeleteAtIntoIntegrationAndAclRole;

class Migration1622782058AddDeleteAtIntoIntegrationAndAclRoleTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->rollBack();

        $migration = new Migration1622782058AddDeleteAtIntoIntegrationAndAclRole();
        $migration->update($this->connection);

        $this->connection->beginTransaction();
    }

    public function testItAddDeletedAtIntoAclRoleAndIntegration(): void
    {
        $deletedAtColumnIntegration = $this->connection->fetchColumn(
            'SHOW COLUMNS FROM `integration` WHERE `Field` LIKE :column;',
            ['column' => 'deleted_at']
        );

        static::assertSame('deleted_at', $deletedAtColumnIntegration);

        $deletedAtColumnAclRole = $this->connection->fetchColumn(
            'SHOW COLUMNS FROM `acl_role` WHERE `Field` LIKE :column;',
            ['column' => 'deleted_at']
        );

        static::assertSame('deleted_at', $deletedAtColumnAclRole);
    }
}
