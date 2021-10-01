<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1631863869AddLogEntryCreateAndStateMachineTransitionReadPrivilege;

class Migration1631863869AddLogEntryCreateAndStateMachineTransitionReadPrivilegeTest extends TestCase
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
            'privileges' => ['order.viewer', 'locale:read'],
        ]], $context);

        $migration = new Migration1631863869AddLogEntryCreateAndStateMachineTransitionReadPrivilege();
        $migration->update($connection);

        /** @var AclRoleEntity $role */
        $role = $repo->search(new Criteria([$id]), $context)->first();
        static::assertNotNull($role);

        static::assertContains('state_machine_transition:read', $role->getPrivileges());
        static::assertContains('log_entry:create', $role->getPrivileges());
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $repo = $this->getContainer()->get('acl_role.repository');
        $connection = $this->getContainer()->get(Connection::class);

        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $privileges = ['property.editor'];
        $repo->create([[
            'id' => $id,
            'name' => 'test',
            'privileges' => $privileges,
        ]], $context);

        $before = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        $migration = new Migration1631863869AddLogEntryCreateAndStateMachineTransitionReadPrivilege();
        $migration->update($connection);

        $after = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame($before, $after);
    }
}
