<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
#[Package('framework')]
class WebhookDispatchEndToEndTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testAsyncWebhookDispatchesExactlyOneMessage(): void
    {
        $webhookId = Uuid::randomHex();
        $webhookUrl = 'https://example.com/webhook';

        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, $webhookUrl);

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        $manager->dispatch($event);

        static::assertSame(1, $this->getDispatchedMessageCount(WebhookEventMessage::class));

        $messages = $this->getDispatchedMessages();
        static::assertCount(1, $messages);

        $message = $messages[0];
        static::assertSame($webhookId, $message->getWebhookId());
        static::assertSame($webhookUrl, $message->getUrl());
        static::assertNull($message->getAppId());
        static::assertSame(WebhookEventMessage::DEFAULT_PARTITION_KEY, $message->getPartitionKey());

        $payload = $message->getPayload();
        static::assertArrayHasKey('source', $payload);
        static::assertArrayHasKey('data', $payload);
        static::assertArrayHasKey('payload', $payload['data']);
        static::assertSame('test@example.com', $payload['data']['payload']['email']);
    }

    public function testAsyncWebhookDispatchCreatesOutboxEntry(): void
    {
        $webhookId = Uuid::randomHex();

        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        $manager->dispatch($event);

        $messages = $this->getDispatchedMessages();
        static::assertCount(1, $messages);

        $webhookEventId = $messages[0]->getWebhookEventId();

        $eventLog = $this->connection->fetchAssociative(
            'SELECT * FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );

        static::assertIsArray($eventLog, 'Expected webhook_event_log entry to exist');
        static::assertSame(
            WebhookEventLogDefinition::STATUS_QUEUED,
            $eventLog['delivery_status']
        );
        static::assertSame(CustomerBeforeLoginEvent::EVENT_NAME, $eventLog['event_name']);

        $delivery = $this->connection->fetchAssociative(
            'SELECT * FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );

        static::assertIsArray($delivery, 'Expected webhook_delivery entry to exist');
        static::assertSame(
            WebhookEventLogDefinition::STATUS_QUEUED,
            $delivery['delivery_status']
        );
        static::assertSame(
            Uuid::fromHexToBytes($webhookId),
            $delivery['webhook_id']
        );
    }

    public function testNoWebhookRegisteredDispatchesNoMessage(): void
    {
        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        $manager->dispatch($event);

        static::assertSame(0, $this->getDispatchedMessageCount(WebhookEventMessage::class));
    }

    public function testMultipleWebhooksForSameEventDispatchMultipleMessages(): void
    {
        $webhookId1 = Uuid::randomHex();
        $webhookId2 = Uuid::randomHex();

        $this->createWebhook($webhookId1, 'webhook-1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/hook1');
        $this->createWebhook($webhookId2, 'webhook-2', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/hook2');

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        $manager->dispatch($event);

        static::assertSame(2, $this->getDispatchedMessageCount(WebhookEventMessage::class));

        $messages = $this->getDispatchedMessages();
        static::assertCount(2, $messages);

        $dispatchedWebhookIds = array_map(
            static fn (WebhookEventMessage $m) => $m->getWebhookId(),
            $messages
        );

        sort($dispatchedWebhookIds);
        $expectedIds = [$webhookId1, $webhookId2];
        sort($expectedIds);

        static::assertSame($expectedIds, $dispatchedWebhookIds);
    }

    public function testSyncPathDoesNotDispatchToQueue(): void
    {
        $webhookId = Uuid::randomHex();

        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: true);
        $event = $this->createCustomerBeforeLoginEvent();

        $manager->dispatch($event);

        static::assertSame(0, $this->getDispatchedMessageCount(WebhookEventMessage::class));

        $eventLogs = $this->connection->fetchAllAssociative(
            'SELECT * FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'test-webhook']
        );

        static::assertCount(1, $eventLogs);
        static::assertSame(
            WebhookEventLogDefinition::STATUS_SUCCESS,
            $eventLogs[0]['delivery_status']
        );
    }

    public function testAsyncWebhookIsDeliveredWhenWorkerRuns(): void
    {
        $webhookId = Uuid::randomHex();
        $webhookUrl = 'https://example.com/webhook';

        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, $webhookUrl);

        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        $manager->dispatch($event);

        static::assertSame(1, $this->getDispatchedMessageCount(WebhookEventMessage::class));

        $this->runWorker();

        $request = $this->getLastRequest();
        static::assertNotNull($request, 'Expected an HTTP request to be made by the worker');
        static::assertSame('POST', $request->getMethod());

        $eventLogs = $this->connection->fetchAllAssociative(
            'SELECT * FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'test-webhook']
        );

        static::assertCount(1, $eventLogs);
        static::assertSame(
            WebhookEventLogDefinition::STATUS_SUCCESS,
            $eventLogs[0]['delivery_status']
        );

        $deliveryCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_id = :webhookId',
            ['webhookId' => Uuid::fromHexToBytes($webhookId)]
        );

        static::assertSame(0, $deliveryCount, 'Delivery row should be cleaned up after successful delivery');
    }

    private function getWebhookManager(bool $isAdminWorkerEnabled = false): WebhookManager
    {
        $guzzle = static::getContainer()->get('shopware.webhook.guzzle');

        return new WebhookManager(
            static::getContainer()->get(WebhookLoader::class),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(HookableEventFactory::class),
            static::getContainer()->get(AppLocaleProvider::class),
            static::getContainer()->get(AppPayloadServiceHelper::class),
            new WebhookClient($guzzle, static::getContainer()->get(ClockInterface::class)),
            static::getContainer()->get('messenger.default_bus'),
            $_SERVER['APP_URL'],
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $isAdminWorkerEnabled,
            static::getContainer()->get(OutboxEventRepository::class),
            static::getContainer()->get(WebhookDeliveryService::class),
        );
    }

    private function createWebhook(string $webhookId, string $name, string $eventName, string $url): void
    {
        $this->connection->insert('webhook', [
            'id' => Uuid::fromHexToBytes($webhookId),
            'name' => $name,
            'event_name' => $eventName,
            'url' => $url,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createCustomerBeforeLoginEvent(): CustomerBeforeLoginEvent
    {
        return new CustomerBeforeLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );
    }

    /**
     * @return list<WebhookEventMessage>
     */
    private function getDispatchedMessages(): array
    {
        $bus = static::getContainer()->get('messenger.bus.test_shopware');
        \assert($bus instanceof TraceableMessageBus);

        $messages = [];
        foreach ($bus->getDispatchedMessages() as $entry) {
            if (isset($entry['message']) && $entry['message'] instanceof WebhookEventMessage) {
                $messages[] = $entry['message'];
            }
        }

        return $messages;
    }
}
