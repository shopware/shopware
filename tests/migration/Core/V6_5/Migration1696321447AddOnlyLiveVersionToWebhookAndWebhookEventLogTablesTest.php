<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1696321447AddOnlyLiveVersionToWebhookAndWebhookEventLogTables;

/**
 * @package content
 *
 * @internal
 */
#[CoversClass(Migration1696321447AddOnlyLiveVersionToWebhookAndWebhookEventLogTables::class)]
class Migration1696321447AddOnlyLiveVersionToWebhookAndWebhookEventLogTablesTest extends TestCase
{
    public function testMultipleExecution(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1696321447AddOnlyLiveVersionToWebhookAndWebhookEventLogTables();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'webhook', 'only_live_version'));
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'webhook_event_log', 'only_live_version'));
    }

    public function testColumnsGetCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1696321447AddOnlyLiveVersionToWebhookAndWebhookEventLogTables();

        if (EntityDefinitionQueryHelper::columnExists($connection, 'webhook', 'only_live_version')) {
            $connection->executeStatement('ALTER TABLE `webhook` DROP `only_live_version`;');
        }

        if (EntityDefinitionQueryHelper::columnExists($connection, 'webhook_event_log', 'only_live_version')) {
            $connection->executeStatement('ALTER TABLE `webhook_event_log` DROP `only_live_version`;');
        }

        $migration->update($connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'webhook', 'only_live_version'));
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'webhook_event_log', 'only_live_version'));
    }

    public function testPartialExecutionForWebhook(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1696321447AddOnlyLiveVersionToWebhookAndWebhookEventLogTables();

        $migration->update($connection);

        $connection->executeStatement('ALTER TABLE `webhook` DROP `only_live_version`;');

        $migration->update($connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'webhook', 'only_live_version'));
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'webhook_event_log', 'only_live_version'));
    }

    public function testPartialExecutionForWebhookEventLog(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1696321447AddOnlyLiveVersionToWebhookAndWebhookEventLogTables();

        $migration->update($connection);

        $connection->executeStatement('ALTER TABLE `webhook_event_log` DROP `only_live_version`;');

        $migration->update($connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'webhook', 'only_live_version'));
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'webhook_event_log', 'only_live_version'));
    }
}
