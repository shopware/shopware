<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1642732351AddAppFlowActionId;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1642732351AddAppFlowActionId
 */
class Migration1642732351AddAppFlowActionIdTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1642732351AddAppFlowActionId $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->migration = new Migration1642732351AddAppFlowActionId();
    }

    public function testMigration(): void
    {
        $this->connection->rollBack();
        $this->prepare();
        $this->migration->update($this->connection);
        $this->connection->beginTransaction();

        $appFlowActionIdColumnExists = $this->hasColumn('flow_sequence', 'app_flow_action_id');
        static::assertTrue($appFlowActionIdColumnExists);
    }

    public function testIsExistsAppFlowActionId(): void
    {
        $aclRoleId = Uuid::randomHex();
        $this->createAclRole($aclRoleId);

        $appId = Uuid::randomHex();
        $this->createApp($appId, $aclRoleId);

        $flowAppId = Uuid::randomHex();
        $this->createAppFlowAction($flowAppId, $appId);

        $flowId = Uuid::randomHex();
        $this->createFlow($flowId);

        $sequenceId = Uuid::randomHex();
        $this->createSequence($sequenceId, $flowId, $flowAppId);

        static::assertEquals($flowAppId, $this->connection->executeQuery('
        SELECT LOWER(HEX(app_flow_action_id)) FROM `flow_sequence` WHERE `id` = :id', ['id' => Uuid::fromHexToBytes($sequenceId)])->fetchOne());
    }

    private function createApp(string $appId, string $aclRoleId): void
    {
        $this->connection->insert('app', [
            'id' => Uuid::fromHexToBytes($appId),
            'name' => 'flowbuilderactionapp',
            'active' => 1,
            'path' => 'custom/apps/flowbuilderactionapp',
            'version' => '1.0.0',
            'configurable' => 0,
            'app_secret' => 'appSecret',
            'acl_role_id' => Uuid::fromHexToBytes($aclRoleId),
            'integration_id' => $this->getIntegrationId(),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAppFlowAction(string $flowAppId, string $appId): void
    {
        $this->connection->insert('app_flow_action', [
            'id' => Uuid::fromHexToBytes($flowAppId),
            'app_id' => Uuid::fromHexToBytes($appId),
            'name' => 'telegram.send.message',
            'badge' => 'Telegram',
            'url' => 'https://example.xyz',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getIntegrationId(): string
    {
        $integrationId = Uuid::randomBytes();

        $this->connection->insert('integration', [
            'id' => $integrationId,
            'access_key' => 'test',
            'secret_access_key' => 'test',
            'label' => 'test',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $integrationId;
    }

    private function createAclRole(string $aclRoleId): void
    {
        $this->connection->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($aclRoleId),
            'name' => 'aclTest',
            'privileges' => json_encode(['users_and_permissions.viewer']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createFlow(string $flowId): void
    {
        $this->connection->insert('flow', [
            'id' => Uuid::fromHexToBytes($flowId),
            'name' => 'Test Flow',
            'event_name' => 'checkout.order.placed',
            'priority' => 1,
            'active' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createSequence(string $sequenceId, string $flowId, string $appFlowActionId): void
    {
        $this->connection->insert('flow_sequence', [
            'id' => Uuid::fromHexToBytes($sequenceId),
            'flow_id' => Uuid::fromHexToBytes($flowId),
            'app_flow_action_id' => Uuid::fromHexToBytes($appFlowActionId),
            'action_name' => 'app.telegram.send.message',
            'position' => 1,
            'display_group' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function prepare(): void
    {
        $appFlowActionIdColumnExists = $this->hasColumn('flow_sequence', 'app_flow_action_id');

        if ($appFlowActionIdColumnExists) {
            $this->connection->executeStatement('ALTER TABLE `flow_sequence` DROP FOREIGN KEY `fk.flow_sequence.app_flow_action_id`');
            $this->connection->executeStatement('ALTER TABLE `flow_sequence` DROP COLUMN `app_flow_action_id`, DROP INDEX `fk.flow_sequence.app_flow_action_id`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \count(array_filter(
            $this->connection->getSchemaManager()->listTableColumns($table),
            static fn (Column $column): bool => $column->getName() === $columnName
        )) > 0;
    }
}
