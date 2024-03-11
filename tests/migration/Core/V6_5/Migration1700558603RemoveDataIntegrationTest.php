<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1700558603RemoveDataIntegration;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[CoversClass(Migration1700558603RemoveDataIntegration::class)]
class Migration1700558603RemoveDataIntegrationTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testExecutesIfSystemConfigIsNotPresent(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `system_config` WHERE configuration_key = :configurationKey',
            ['configurationKey' => Migration1700558603RemoveDataIntegration::SYSTEM_CONFIG_KEY]
        );

        static::assertFalse($this->isSystemConfigPresentInDatabase());

        $migration = new Migration1700558603RemoveDataIntegration();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertFalse($this->isSystemConfigPresentInDatabase());
    }

    public function testRemovesIntegrationAndSystemConfigIfPresent(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `system_config` WHERE configuration_key = :configurationKey',
            ['configurationKey' => Migration1700558603RemoveDataIntegration::SYSTEM_CONFIG_KEY]
        );

        static::assertFalse($this->isSystemConfigPresentInDatabase());

        $this->getContainer()->get(SystemConfigService::class)->set(
            Migration1700558603RemoveDataIntegration::SYSTEM_CONFIG_KEY,
            [
                'integrationId' => $integrationId = $this->createIntegration(),
                'appUrl' => 'https://shopware.swag',
                'shopId' => 'shop_id',
            ]
        );

        static::assertTrue($this->isIntegrationPresentInDatabase($integrationId));
        static::assertTrue($this->isSystemConfigPresentInDatabase());

        $migration = new Migration1700558603RemoveDataIntegration();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertFalse($this->isIntegrationPresentInDatabase($integrationId));
        static::assertFalse($this->isSystemConfigPresentInDatabase());
    }

    private function isSystemConfigPresentInDatabase(): bool
    {
        return $this->connection->executeQuery(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :configurationKey',
            ['configurationKey' => Migration1700558603RemoveDataIntegration::SYSTEM_CONFIG_KEY]
        )->fetchOne() !== false;
    }

    private function isIntegrationPresentInDatabase(string $id): bool
    {
        return $this->connection->executeQuery(
            'SELECT `id` FROM `system_config` WHERE `configuration_key` = :configurationKey',
            ['configurationKey' => Migration1700558603RemoveDataIntegration::SYSTEM_CONFIG_KEY]
        )->fetchOne() !== false;
    }

    private function createIntegration(): string
    {
        $integrationId = Uuid::randomHex();
        $this->connection->insert(
            'integration',
            [
                'id' => Uuid::fromHexToBytes($integrationId),
                'label' => 'test',
                'access_key' => 'test',
                'secret_access_key' => 'test',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        return $integrationId;
    }
}
