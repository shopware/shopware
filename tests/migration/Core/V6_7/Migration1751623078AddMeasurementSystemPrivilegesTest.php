<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1751623078AddMeasurementSystemPrivileges;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1751623078AddMeasurementSystemPrivileges::class)]
class Migration1751623078AddMeasurementSystemPrivilegesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1751623078AddMeasurementSystemPrivileges $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1751623078AddMeasurementSystemPrivileges();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1751623078, $this->migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->prepareTestData();

        // Run the migration
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        // Check that privileges were added to the correct roles
        $this->assertPrivilegesExist('Measurement Viewer', [
            'measurement.viewer',
            'system_config:read',
            'measurement_system:read',
            'measurement_display_unit:read',
        ]);

        $this->assertPrivilegesExist('Measurement Editor', [
            'measurement.editor',
            'system_config:update',
        ]);

        $this->assertPrivilegesExist('Measurement Creator', [
            'measurement.creator',
            'measurement_system:create',
            'measurement_display_unit:create',
        ]);

        $this->assertPrivilegesExist('Measurement Deleter', [
            'measurement.deleter',
            'measurement_system:delete',
            'measurement_display_unit:delete',
        ]);
    }

    private function prepareTestData(): void
    {
        $roles = [
            'Measurement Viewer' => ['measurement.viewer'],
            'Measurement Editor' => ['measurement.editor'],
            'Measurement Creator' => ['measurement.creator'],
            'Measurement Deleter' => ['measurement.deleter'],
        ];

        foreach ($roles as $name => $privileges) {
            $this->connection->insert('acl_role', [
                'id' => Uuid::randomBytes(),
                'name' => $name,
                'privileges' => json_encode($privileges),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * @param array<string> $expectedPrivileges
     */
    private function assertPrivilegesExist(string $roleName, array $expectedPrivileges): void
    {
        $privileges = $this->connection->fetchOne(
            'SELECT privileges FROM acl_role WHERE name = :name',
            ['name' => $roleName]
        );

        static::assertNotFalse($privileges);
        $decodedPrivileges = json_decode($privileges, true);

        foreach ($expectedPrivileges as $privilege) {
            static::assertContains($privilege, $decodedPrivileges, "Privilege '{$privilege}' not found in role '{$roleName}'");
        }
    }
}
