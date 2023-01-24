<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1668677456AddAppReadPrivilegeForIntegrationRoles;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1668677456AddAppReadPrivilegeForIntegrationRoles
 */
class Migration1668677456AddAppPrivilegesForIntegrationRolesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testPrivilegesAreAddedToRole(): void
    {
        $roleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test integration viewer',
            'privileges' => \json_encode(['integration.viewer']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1668677456AddAppReadPrivilegeForIntegrationRoles();
        $migration->update($this->connection);

        $privileges = $this->connection->fetchOne('
            SELECT privileges FROM acl_role WHERE id = :id
        ', [
            'id' => $roleId,
        ]);

        $privileges = \json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotFalse($privileges);
        static::assertContains('app:read', $privileges);
    }
}
