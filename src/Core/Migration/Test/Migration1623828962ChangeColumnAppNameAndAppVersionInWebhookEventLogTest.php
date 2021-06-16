<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Migration\V6_4\Migration1623828962ChangeColumnAppNameAndAppVersionInWebhookEventLog;

class Migration1623828962ChangeColumnAppNameAndAppVersionInWebhookEventLogTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->rollBack();
        $this->connection->executeUpdate('
            ALTER TABLE `webhook_event_log`
                MODIFY COLUMN `app_name` VARCHAR(255) NOT NULL,
                MODIFY COLUMN `app_version` VARCHAR(255) NOT NULL;
        ');

        $migration = new Migration1623828962ChangeColumnAppNameAndAppVersionInWebhookEventLog();
        $migration->update($this->connection);

        $this->connection->beginTransaction();
    }

    public function testItChangeColumnAppNameAndAppVersionSuccessfully(): void
    {
        $this->connection->exec('DELETE FROM webhook_event_log');

        $context = Context::createDefaultContext();

        $webhookEventId = Uuid::randomHex();
        $webhookId = Uuid::randomHex();

        $webhookEventMessage = new WebhookEventMessage($webhookEventId, [], null, $webhookId, '6.4', 'http://test.com');

        $webhookEventLogRepo = $this->getContainer()->get('webhook_event_log.repository');
        $webhookEventLogRepo->create([[
            'id' => $webhookEventId,
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'webhookName',
            'eventName' => 'eventName',
            'url' => 'http://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], $context);

        $webhookEventLogs = $webhookEventLogRepo->search(new Criteria(), $context)->getEntities();

        static::assertCount(1, $webhookEventLogs);
    }
}
