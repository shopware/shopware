<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1620733405UpdateRolePrivilegesForDistinguishablePaymentName;

class Migration1620733405UpdateRolePrivilegesForDistinguishablePaymentNameTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNewPermissionsAreAdded(): void
    {
        $repo = $this->getContainer()->get('acl_role.repository');
        $connection = $this->getContainer()->get(Connection::class);

        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $repo->create([[
            'id' => $id,
            'name' => 'test',
            'privileges' => ['payment.viewer'],
        ]], $context);

        $migration = new Migration1620733405UpdateRolePrivilegesForDistinguishablePaymentName();
        $migration->update($connection);

        /** @var AclRoleEntity $role */
        $role = $repo->search(new Criteria([$id]), $context)->first();
        static::assertNotNull($role);

        static::assertContains('app:read', $role->getPrivileges());
        static::assertContains('app_payment_method:read', $role->getPrivileges());
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $repo = $this->getContainer()->get('acl_role.repository');
        $connection = $this->getContainer()->get(Connection::class);

        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $repo->create([[
            'id' => $id,
            'name' => 'test',
            'privileges' => ['property.editor'],
        ]], $context);

        $before = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        $migration = new Migration1620733405UpdateRolePrivilegesForDistinguishablePaymentName();
        $migration->update($connection);

        $after = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame($before, $after);
    }

    public function testStringKeysInPrivilegesDontBreakMigration(): void
    {
        $repo = $this->getContainer()->get('acl_role.repository');
        $connection = $this->getContainer()->get(Connection::class);

        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $repo->create([[
            'id' => $id,
            'name' => 'test',
            'privileges' => ['property.editor'],
        ]], $context);
        // Privileges may have this structure if they are created from a mysql dump
        $connection->executeStatement('UPDATE `acl_role` SET `privileges` = "{\"0\": \"property.editor\"}"');

        $before = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        $migration = new Migration1620733405UpdateRolePrivilegesForDistinguishablePaymentName();
        $migration->update($connection);

        $after = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame($before, $after);
    }

    public function testEmptyPrivilegesDontBreakMigration(): void
    {
        $repo = $this->getContainer()->get('acl_role.repository');
        $connection = $this->getContainer()->get(Connection::class);

        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $repo->create([[
            'id' => $id,
            'name' => 'test',
            'privileges' => [],
        ]], $context);

        $before = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        $migration = new Migration1620733405UpdateRolePrivilegesForDistinguishablePaymentName();
        $migration->update($connection);

        $after = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame($before, $after);
    }
}
