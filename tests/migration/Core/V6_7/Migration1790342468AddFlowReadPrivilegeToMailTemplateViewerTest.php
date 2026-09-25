<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1790342468AddFlowReadPrivilegeToMailTemplateViewer;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1790342468AddFlowReadPrivilegeToMailTemplateViewer::class)]
class Migration1790342468AddFlowReadPrivilegeToMailTemplateViewerTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1790342468AddFlowReadPrivilegeToMailTemplateViewer $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1790342468AddFlowReadPrivilegeToMailTemplateViewer();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1790342468, $this->migration->getCreationTimestamp());
    }

    public function testAddsFlowReadPrivilegesToMailTemplateViewerRoles(): void
    {
        $roleId = $this->createRole(['mail_templates.viewer']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        static::assertContains('mail_templates.viewer', $privileges);
        static::assertContains('flow:read', $privileges);
        static::assertContains('flow_sequence:read', $privileges);
        static::assertCount(1, \array_keys($privileges, 'flow:read', true));
        static::assertCount(1, \array_keys($privileges, 'flow_sequence:read', true));
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $roleId = $this->createRole(['product.viewer']);
        $before = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        $this->migration->update($this->connection);

        $after = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        static::assertSame($before, $after);
    }

    /**
     * @param list<string> $privileges
     */
    private function createRole(array $privileges): string
    {
        $roleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test mail template acl migration',
            'privileges' => \json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $roleId;
    }

    /**
     * @return list<string>
     */
    private function fetchPrivileges(string $roleId): array
    {
        $privileges = $this->connection->fetchOne(
            'SELECT `privileges` FROM `acl_role` WHERE id = :id',
            ['id' => $roleId]
        );

        static::assertIsString($privileges);

        $decodedPrivileges = \json_decode($privileges, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($decodedPrivileges);
        static::assertTrue(\array_is_list($decodedPrivileges));
        foreach ($decodedPrivileges as $decodedPrivilege) {
            static::assertIsString($decodedPrivilege);
        }

        /** @var list<string> $decodedPrivileges */
        return $decodedPrivileges;
    }
}
