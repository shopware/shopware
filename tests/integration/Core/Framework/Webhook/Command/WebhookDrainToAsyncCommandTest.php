<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Command\WebhookDrainToAsyncCommand;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class WebhookDrainToAsyncCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->messageBus = static::getContainer()->get('messenger.default_bus');

        $this->connection->executeStatement('DELETE FROM webhook_stream');
        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook');
    }

    public function testRollbackDrainResetsStuckRowInPlaceAndPreservesSequence(): void
    {
        $this->createWebhook('wh-1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatchEventViaWebhookManager();
        });

        $rowBefore = $this->fetchDeliveryRow('wh-1');
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $rowBefore['delivery_status']);

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function (): void {
            static::assertSame(Command::SUCCESS, $this->runCommand(['--force' => true]));
        });

        $rowAfter = $this->fetchDeliveryRow('wh-1');
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $rowAfter['delivery_status']);
        static::assertNull($rowAfter['next_retry_at']);
        static::assertNull($rowAfter['last_attempt_at']);
        static::assertSame($rowBefore['id'], $rowAfter['id'], 'delivery row id (== event_log.sequence) must not change');
    }

    public function testRollbackDrainClearsPendingRetryTimestamps(): void
    {
        $this->createWebhook('wh-1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatchEventViaWebhookManager();
        });

        // Simulate a delivery that the rework consumer attempted and parked for retry.
        $this->connection->executeStatement(
            'UPDATE webhook_delivery
             SET delivery_status = :status,
                 last_attempt_at = :lastAttempt,
                 next_retry_at = :nextRetry,
                 execution_count = 1',
            [
                'status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY,
                'lastAttempt' => (new \DateTimeImmutable('-30 seconds'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'nextRetry' => (new \DateTimeImmutable('+5 minutes'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $this->connection->executeStatement(
            'UPDATE webhook_event_log SET delivery_status = :status',
            ['status' => WebhookEventLogDefinition::STATUS_PENDING_RETRY]
        );

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function (): void {
            static::assertSame(Command::SUCCESS, $this->runCommand(['--force' => true]));
        });

        $row = $this->fetchDeliveryRow('wh-1');
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $row['delivery_status']);
        static::assertNull($row['next_retry_at']);
        static::assertNull($row['last_attempt_at']);
    }

    public function testRollbackDrainLeavesRunningRowsUntouched(): void
    {
        $this->createWebhook('wh-1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatchEventViaWebhookManager();
        });

        $now = (new \DateTimeImmutable('-10 seconds'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->executeStatement(
            'UPDATE webhook_delivery
             SET delivery_status = :status,
                 last_attempt_at = :now,
                 execution_count = 1',
            ['status' => WebhookEventLogDefinition::STATUS_RUNNING, 'now' => $now]
        );
        $this->connection->executeStatement(
            'UPDATE webhook_event_log SET delivery_status = :status',
            ['status' => WebhookEventLogDefinition::STATUS_RUNNING]
        );

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function (): void {
            static::assertSame(Command::SUCCESS, $this->runCommand(['--force' => true]));
        });

        $row = $this->fetchDeliveryRow('wh-1');
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $row['delivery_status']);
        static::assertNotNull($row['last_attempt_at'], 'running row last_attempt_at must not be reset');
    }

    public function testRollbackDrainRefusesToRunWhileFlagIsActive(): void
    {
        $this->createWebhook('wh-1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatchEventViaWebhookManager();

            static::assertSame(Command::FAILURE, $this->runCommand(['--force' => true]));
        });

        // Row must remain QUEUED — the drain refused before touching anything.
        $row = $this->fetchDeliveryRow('wh-1');
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $row['delivery_status']);
    }

    public function testRollbackDrainAbortsWhenConfirmationDeclined(): void
    {
        $this->createWebhook('wh-1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatchEventViaWebhookManager();
        });

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function (): void {
            $tester = new CommandTester($this->makeCommand());
            $tester->setInputs(['no']);

            static::assertSame(Command::SUCCESS, $tester->execute([]));
            static::assertStringContainsString('Aborting due to user input.', $tester->getDisplay());
        });

        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $this->fetchDeliveryRow('wh-1')['delivery_status']);
    }

    public function testRollbackDrainMarksCorruptSerializedMessageFailedAndDeletesIt(): void
    {
        $this->createWebhook('wh-1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->dispatchEventViaWebhookManager();
        });

        $eventLogId = $this->fetchEventLogIdByWebhookName('wh-1');
        $this->connection->executeStatement(
            'UPDATE webhook_event_log SET serialized_webhook_message = :garbage WHERE id = :id',
            ['garbage' => 'not a valid serialized payload', 'id' => $eventLogId]
        );

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function (): void {
            static::assertSame(Command::SUCCESS, $this->runCommand(['--force' => true]));
        });

        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $this->fetchEventLogStatusByWebhookName('wh-1'));
        static::assertFalse($this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $eventLogId]
        ));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runCommand(array $options): int
    {
        return (new CommandTester($this->makeCommand()))->execute($options);
    }

    private function makeCommand(): WebhookDrainToAsyncCommand
    {
        return new WebhookDrainToAsyncCommand($this->connection, $this->messageBus, new NullLogger());
    }

    /**
     * Persists a webhook the way production does — through a registered app — so the
     * outbox row produced by `WebhookManager::dispatch` carries the same partition key
     * and signing material the rework consumer would emit.
     */
    private function createWebhook(string $name, string $eventName, string $url): void
    {
        $unique = Uuid::randomHex();
        $aclRoleId = Uuid::randomBytes();
        $integrationId = Uuid::randomBytes();
        $appId = Uuid::randomBytes();
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('acl_role', [
            'id' => $aclRoleId,
            'name' => 'role-' . $unique,
            'privileges' => json_encode([], \JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        $this->connection->insert('integration', [
            'id' => $integrationId,
            'access_key' => 'key-' . $unique,
            'secret_access_key' => 'secret-' . $unique,
            'label' => 'integration-' . $unique,
            'created_at' => $now,
        ]);

        $this->connection->insert('app', [
            'id' => $appId,
            'name' => 'app-' . $unique,
            'path' => '/dev/null',
            'version' => '1.0.0',
            'active' => 1,
            'app_secret' => 'app-secret-' . $unique,
            'integration_id' => $integrationId,
            'acl_role_id' => $aclRoleId,
            'created_at' => $now,
        ]);

        $this->connection->insert('webhook', [
            'id' => Uuid::randomBytes(),
            'name' => $name,
            'event_name' => $eventName,
            'url' => $url,
            'app_id' => $appId,
            'created_at' => $now,
        ]);
    }

    private function dispatchEventViaWebhookManager(): void
    {
        $manager = $this->buildWebhookManager();
        $event = new CustomerBeforeLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );

        $manager->dispatch($event);
    }

    private function buildWebhookManager(): WebhookManager
    {
        $guzzle = static::getContainer()->get('shopware.webhook.guzzle');
        $clock = static::getContainer()->get(ClockInterface::class);
        $webhookClient = new WebhookClient($guzzle, $clock);

        $deliveryService = new WebhookDeliveryService(
            $webhookClient,
            static::getContainer()->get(AppPayloadServiceHelper::class),
            static::getContainer()->get(WebhookOutboxStore::class),
            static::getContainer()->get(RetryDelayCalculator::class),
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(WebhookHealthService::class),
            static::getContainer()->get('logger'),
            false,
        );

        return new WebhookManager(
            static::getContainer()->get(WebhookLoader::class),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(HookableEventFactory::class),
            static::getContainer()->get(AppLocaleProvider::class),
            static::getContainer()->get(AppPayloadServiceHelper::class),
            $webhookClient,
            static::getContainer()->get('messenger.default_bus'),
            $_SERVER['APP_URL'],
            Kernel::SHOPWARE_FALLBACK_VERSION,
            false,
            $deliveryService,
            static::getContainer()->get(WebhookOutboxStore::class),
        );
    }

    /**
     * @return array{id: int, delivery_status: string, last_attempt_at: ?string, next_retry_at: ?string}
     */
    private function fetchDeliveryRow(string $webhookName): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT d.id, d.delivery_status, d.last_attempt_at, d.next_retry_at
             FROM webhook_delivery d
             JOIN webhook_event_log el ON el.id = d.webhook_event_log_id
             WHERE el.webhook_name = :name',
            ['name' => $webhookName]
        );

        static::assertIsArray($row, \sprintf('no webhook_delivery row found for "%s"', $webhookName));

        return [
            'id' => (int) $row['id'],
            'delivery_status' => (string) $row['delivery_status'],
            'last_attempt_at' => $row['last_attempt_at'] !== null ? (string) $row['last_attempt_at'] : null,
            'next_retry_at' => $row['next_retry_at'] !== null ? (string) $row['next_retry_at'] : null,
        ];
    }

    private function fetchEventLogIdByWebhookName(string $webhookName): string
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => $webhookName]
        );
        static::assertIsString($id);

        return $id;
    }

    private function fetchEventLogStatusByWebhookName(string $webhookName): string
    {
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => $webhookName]
        );
        static::assertIsString($status);

        return $status;
    }
}
