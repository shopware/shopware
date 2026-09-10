<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\HttpErrorClassifier;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class WebhookDispatchGateTest extends TestCase
{
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();
        $this->cleanupWebhookTables();
    }

    protected function tearDown(): void
    {
        $this->cleanupWebhookTables();
    }

    public function testRoutesDegradedAndHealthyWebhooksIndependently(): void
    {
        $this->seedWebhook('wh-degraded', CustomerBeforeLoginEvent::EVENT_NAME);
        $this->seedWebhook('wh-healthy', CustomerBeforeLoginEvent::EVENT_NAME);
        $this->seedHealth('wh-degraded', EndpointState::Degraded, [
            'consecutive_transient_failures' => 5,
            'cooldown_until' => (new \DateTimeImmutable('+5 minutes'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($event): void {
            $this->getManager()->dispatch($event);
        });

        static::assertSame(
            [
                'wh-degraded' => WebhookEventLogDefinition::STATUS_PAUSED,
                'wh-healthy' => WebhookEventLogDefinition::STATUS_QUEUED,
            ],
            $this->connection->fetchAllKeyValue(
                'SELECT webhook.name, webhook_delivery.delivery_status
                 FROM webhook_delivery
                 INNER JOIN webhook ON webhook.id = webhook_delivery.webhook_id
                 ORDER BY webhook.name'
            )
        );

        static::assertSame(
            [
                'wh-degraded' => WebhookEventLogDefinition::STATUS_PAUSED,
                'wh-healthy' => WebhookEventLogDefinition::STATUS_QUEUED,
            ],
            $this->connection->fetchAllKeyValue(
                'SELECT webhook_name, delivery_status
                 FROM webhook_event_log
                 ORDER BY webhook_name'
            )
        );

        static::assertFalse(
            $this->connection->fetchOne(
                'SELECT 1 FROM webhook_health WHERE webhook_id = :id',
                ['id' => $this->ids->getBytes('wh-healthy')]
            )
        );
    }

    // Use async delivery so queued and held rows remain observable.
    private function getManager(): WebhookManager
    {
        $isAdminWorkerEnabled = false;
        $healthService = static::getContainer()->get(WebhookHealthService::class);
        $webhookClient = new WebhookClient(
            static::getContainer()->get('shopware.webhook.guzzle'),
            static::getContainer()->get(ClockInterface::class),
        );

        $deliveryService = new WebhookDeliveryService(
            $webhookClient,
            static::getContainer()->get(AppPayloadServiceHelper::class),
            static::getContainer()->get(WebhookSigningSecretResolver::class),
            static::getContainer()->get(WebhookOutboxStore::class),
            static::getContainer()->get(RetryDelayCalculator::class),
            static::getContainer()->get('messenger.default_bus'),
            $healthService,
            static::getContainer()->get('logger'),
            new HttpErrorClassifier(),
            $isAdminWorkerEnabled,
        );

        return new WebhookManager(
            static::getContainer()->get(WebhookLoader::class),
            static::getContainer()->get(HookableEventFactory::class),
            static::getContainer()->get(AppLocaleProvider::class),
            static::getContainer()->get(AppPayloadServiceHelper::class),
            $webhookClient,
            static::getContainer()->get('messenger.default_bus'),
            $_SERVER['APP_URL'],
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $isAdminWorkerEnabled,
            $deliveryService,
            static::getContainer()->get(WebhookOutboxStore::class),
            $healthService,
        );
    }

    private function createCustomerBeforeLoginEvent(): CustomerBeforeLoginEvent
    {
        return new CustomerBeforeLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );
    }

    private function seedWebhook(string $key, string $eventName, bool $active = true): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes($key),
            'name' => $key,
            'event_name' => $eventName,
            'url' => 'https://example.com/webhook',
            'active' => (int) $active,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function seedHealth(string $key, EndpointState $state, array $extra = []): void
    {
        $defaults = [
            'webhook_id' => $this->ids->getBytes($key),
            'endpoint_state' => $state->value,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->connection->insert('webhook_health', array_merge($defaults, $extra));
    }

    private function cleanupWebhookTables(): void
    {
        $this->connection->executeStatement('DELETE FROM webhook_stream');
        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook_health');
        $this->connection->executeStatement('DELETE FROM webhook');
    }
}
