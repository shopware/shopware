<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1657027979AddOrderRefundPrivilegeForOrderEditor;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1657027979AddOrderRefundPrivilegeForOrderEditor
 */
class Migration1657027979AddOrderRefundPrivilegeForOrderEditorTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testNewPermissionsAreAdded(): void
    {
        $repo = $this->getContainer()->get('acl_role.repository');
        $connection = $this->getContainer()->get(Connection::class);

        $migration = new Migration1657027979AddOrderRefundPrivilegeForOrderEditor();

        /**
         * @var string $role
         * @var array<int, string> $privileges
         */
        foreach (Migration1657027979AddOrderRefundPrivilegeForOrderEditor::NEW_PRIVILEGES as $role => $privileges) {
            $id = Uuid::randomHex();
            $context = Context::createDefaultContext();
            $repo->create([[
                'id' => $id,
                'name' => 'test',
                'privileges' => [$role],
            ]], $context);

            $migration->update($connection);
            $migration->update($connection);

            /** @var AclRoleEntity $role */
            $role = $repo->search(new Criteria([$id]), $context)->first();
            static::assertNotNull($role);

            /** @var string $privilege */
            foreach ($privileges as $privilege) {
                static::assertContains($privilege, $role->getPrivileges());
            }
        }
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

        $migration = new Migration1657027979AddOrderRefundPrivilegeForOrderEditor();
        $migration->update($connection);
        $migration->update($connection);

        $after = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        static::assertSame($before, $after);
    }
}
