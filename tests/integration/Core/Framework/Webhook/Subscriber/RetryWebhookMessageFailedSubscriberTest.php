<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogCollection;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Subscriber\RetryWebhookMessageFailedSubscriber;
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * @internal
 */
class RetryWebhookMessageFailedSubscriberTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    private WebhookOutboxStore $webhookOutboxStore;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->webhookOutboxStore = static::getContainer()->get(WebhookOutboxStore::class);
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testHandleWebhookMessageFailed(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $appRepository = static::getContainer()->get('app.repository');
        /** @var EntityRepository<WebhookEventLogCollection> $webhookEventLogRepository */
        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');

        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'id' => $webhookId,
                    'name' => 'hook1',
                    'eventName' => 'order',
                    'url' => 'https://test.com',
                ],
            ],
        ]], $this->context);

        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], $this->context);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );

        $this->failWithRetrySubscriber($event);

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), $this->context)
            ->getEntities()
            ->first();
        static::assertNotNull($webhookEventLog);
        static::assertSame($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_FAILED);

        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();

        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(1, $webhook->getErrorCount());
        static::assertTrue($webhook->isActive());
    }

    public function testNonWebhookEventMessageIsIgnored(): void
    {
        $event = new WorkerMessageFailedEvent(
            new Envelope(new \stdClass()),
            'async',
            new \RuntimeException('not a webhook')
        );

        $eventLogCountBefore = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM webhook_event_log');

        $this->failWithRetrySubscriber($event);

        $eventLogCountAfter = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM webhook_event_log');
        static::assertSame($eventLogCountBefore, $eventLogCountAfter);
    }

    /**
     * Webhook deleted between dispatch and final retry: webhook fetch returns no rows, the
     * is_array() guard early-returns before relatedWebhooks->updateRelated would throw on
     * the missing FK. Pins trunk's "no-throw on missing webhook" contract — uncovered on trunk.
     */
    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testTerminalFailureWithDeletedWebhookDoesNotThrow(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        /** @var EntityRepository<WebhookEventLogCollection> $webhookEventLogRepository */
        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');

        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);

        // No webhook row created — simulates deletion after dispatch.
        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], $this->context);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new \RuntimeException('connection reset')
        );

        $this->failWithRetrySubscriber($event);

        $eventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), $this->context)
            ->getEntities()
            ->first();
        static::assertNotNull($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $eventLog->getDeliveryStatus());
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testHandleOldSerializedWebhookMessageWithoutPartitionKey(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId);
        /** @var EntityRepository<WebhookEventLogCollection> $webhookEventLogRepository */
        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventMessage = $this->removePartitionKeyFromSerializedMessage($this->createWebhookEventMessage($webhookEventId, $appId, $webhookId));

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], $this->context);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );

        $this->failWithRetrySubscriber($event);

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), $this->context)
            ->getEntities()
            ->first();
        static::assertNotNull($webhookEventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $webhookEventLog->getDeliveryStatus());
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testHandleWebhookMessageFailedSetsWebhookToInactiveIfErrorCountIsTooHigh(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $appRepository = static::getContainer()->get('app.repository');
        /** @var EntityRepository<WebhookEventLogCollection> $webhookEventLogRepository */
        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');

        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'id' => $webhookId,
                    'name' => 'hook1',
                    'eventName' => 'order',
                    'url' => 'https://test.com',
                    'errorCount' => 9,
                ],
            ],
        ]], $this->context);

        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], $this->context);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );

        $this->failWithRetrySubscriber($event);

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), $this->context)
            ->getEntities()
            ->first();
        static::assertNotNull($webhookEventLog);
        static::assertSame($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_FAILED);

        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();

        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(0, $webhook->getErrorCount());
        static::assertFalse($webhook->isActive());
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testWebhookStaysActiveWithIgnoreStrategy(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $appRepository = static::getContainer()->get('app.repository');
        /** @var EntityRepository<WebhookEventLogCollection> $webhookEventLogRepository */
        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');

        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'id' => $webhookId,
                    'name' => 'hook1',
                    'eventName' => 'order',
                    'url' => 'https://test.com',
                    'errorCount' => 9,
                ],
            ],
        ]], $this->context);

        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], $this->context);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );

        $subscriber = new RetryWebhookMessageFailedSubscriber(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(WebhookOutboxStore::class),
            static::getContainer()->get(RelatedWebhooks::class),
            WebhookFailureStrategy::Ignore->value
        );

        $this->failWithRetrySubscriber($event, $subscriber);

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), $this->context)
            ->getEntities()
            ->first();
        static::assertNotNull($webhookEventLog);
        static::assertSame($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_FAILED);

        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();

        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(10, $webhook->getErrorCount());
        static::assertTrue($webhook->isActive());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function retryDoesNotApplyFailureStrategyProvider(): iterable
    {
        yield 'below threshold' => [0];
        yield 'at threshold' => [9];
    }

    #[DataProvider('retryDoesNotApplyFailureStrategyProvider')]
    public function testRetryDoesNotApplyFailureStrategy(int $startingErrorCount): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, $startingErrorCount);
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);
        $this->createOutboxEntry($webhookEventMessage, $webhookId);

        // Simulate handler: markRunning then resetForRetry (as handler does before throwing)
        $entry = $this->webhookOutboxStore->markRunning($webhookEventId);
        static::assertNotNull($entry);
        $this->webhookOutboxStore->resetForRetry($entry, null);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );
        $event->setForRetry();

        $this->failWithRetrySubscriber($event);

        // Subscriber must not touch event log on retry — status stays QUEUED (set by handler)
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $status);

        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();
        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame($startingErrorCount, $webhook->getErrorCount());
        static::assertTrue($webhook->isActive());
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testTerminalFailureAtThresholdDisablesWebhook(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, 9);
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);
        $this->createOutboxEntry($webhookEventMessage, $webhookId);

        $this->webhookOutboxStore->markRunning($webhookEventId);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );

        $this->failWithRetrySubscriber($event);

        // Event log should be FAILED
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $status);

        // Delivery row deleted
        $deliveryExists = $this->connection->fetchOne(
            'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertFalse($deliveryExists);

        // Webhook disabled at threshold, error_count reset to 0
        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();
        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(0, $webhook->getErrorCount());
        static::assertFalse($webhook->isActive());
    }

    /**
     * @return iterable<string, array{\Closure(self, string): void, string, int}>
     */
    public static function flagOffNewShapeDeliveryProvider(): iterable
    {
        yield 'active running delivery is preserved' => [
            static function (self $self, string $webhookEventId): void {
                $entry = $self->webhookOutboxStore->markRunning($webhookEventId);
                static::assertNotNull($entry);
            },
            WebhookEventLogDefinition::STATUS_RUNNING,
            0,
        ];
        yield 'future pending-retry delivery is preserved' => [
            static function (self $self, string $webhookEventId): void {
                $entry = $self->webhookOutboxStore->markRunning($webhookEventId);
                static::assertNotNull($entry);
                $self->webhookOutboxStore->markPendingRetry($entry, new \DateTimeImmutable('+5 minutes'), null);
            },
            WebhookEventLogDefinition::STATUS_PENDING_RETRY,
            0,
        ];
        yield 'queued-after-reset delivery is marked failed' => [
            static function (self $self, string $webhookEventId): void {
                $entry = $self->webhookOutboxStore->markRunning($webhookEventId);
                static::assertNotNull($entry);
                $self->webhookOutboxStore->resetForRetry($entry, null);
            },
            WebhookEventLogDefinition::STATUS_FAILED,
            1,
        ];
    }

    /**
     * Flag OFF, envelope carries a partitionKey, final Messenger retry. The subscriber routes
     * through markFailedAfterRetryExhaustedIfIdle — only finalize when no other worker owns
     * the delivery:
     * - mid-flight RUNNING → leave alone (active worker is still delivering)
     * - future PENDING_RETRY → leave alone (another worker scheduled the next attempt)
     * - QUEUED after reset → mark FAILED, bump error_count
     */
    #[DataProvider('flagOffNewShapeDeliveryProvider')]
    public function testFlagOffTerminalFailureRespectsNewShapeDeliveryState(
        \Closure $setupDelivery,
        string $expectedEventLogStatus,
        int $expectedErrorCount,
    ): void {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, 0);
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId, partitionKey: $appId);
        $this->createOutboxEntry($webhookEventMessage, $webhookId);

        $setupDelivery($this, $webhookEventId);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($event, $webhookEventId, $webhookId, $expectedEventLogStatus, $expectedErrorCount): void {
            $this->failWithRetrySubscriber($event);

            $eventLogStatus = $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($webhookEventId)]
            );
            static::assertSame($expectedEventLogStatus, $eventLogStatus);

            $webhookRepository = static::getContainer()->get('webhook.repository');
            $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();
            static::assertInstanceOf(WebhookEntity::class, $webhook);
            static::assertSame($expectedErrorCount, $webhook->getErrorCount());
            static::assertTrue($webhook->isActive());
        });
    }

    public function testNoOpWhenOutboxRetriesEnabled(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, 0);

        // Use a message WITHOUT partitionKey (legacy message) — blanket flag-ON early-return
        // means even legacy messages are no-ops when the flag is enabled.
        $webhookEventMessage = new WebhookEventMessage(
            $webhookEventId,
            ['body' => 'payload'],
            $appId,
            $webhookId,
            '6.4',
            'http://example.com',
            's3cr3t',
            Defaults::LANGUAGE_SYSTEM,
            'en-GB',
            // no partitionKey — legacy message
        );

        /** @var EntityRepository<WebhookEventLogCollection> $webhookEventLogRepository */
        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://example.com/hook',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], $this->context);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://example.com/hook'), new Response(500))
        );

        $subscriber = new RetryWebhookMessageFailedSubscriber(
            $this->connection,
            $this->webhookOutboxStore,
            static::getContainer()->get(RelatedWebhooks::class),
            WebhookFailureStrategy::DisableOnThreshold->value,
        );

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($subscriber, $event, $webhookEventId, $webhookId): void {
            $subscriber->failed($event);

            // Subscriber early-returned — event log status must still be QUEUED (no DB writes)
            $status = $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($webhookEventId)]
            );
            static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $status, 'Subscriber must not modify event log when flag is ON');

            $webhookRepository = static::getContainer()->get('webhook.repository');
            $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();

            static::assertInstanceOf(WebhookEntity::class, $webhook);
            static::assertSame(0, $webhook->getErrorCount());
            static::assertTrue($webhook->isActive());
        });
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testIgnoreStrategyKeepsWebhookActiveAboveThreshold(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, 15);
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);
        $this->createOutboxEntry($webhookEventMessage, $webhookId);

        $this->webhookOutboxStore->markRunning($webhookEventId);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(502))
        );

        $subscriber = new RetryWebhookMessageFailedSubscriber(
            $this->connection,
            $this->webhookOutboxStore,
            static::getContainer()->get(RelatedWebhooks::class),
            WebhookFailureStrategy::Ignore->value
        );

        $this->failWithRetrySubscriber($event, $subscriber);

        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();

        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(16, $webhook->getErrorCount());
        static::assertTrue($webhook->isActive());
    }

    public function testTerminalFailureForAlreadySuccessfulEventDoesNotApplyFailureStrategy(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, 0);
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId, partitionKey: $appId);

        /** @var EntityRepository<WebhookEventLogCollection> $webhookEventLogRepository */
        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], $this->context);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($event, $webhookId, $webhookEventId): void {
            static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class)
                ->failed($event);

            $status = $this->connection->fetchOne(
                'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($webhookEventId)]
            );
            static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $status);

            $webhookRepository = static::getContainer()->get('webhook.repository');
            $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();
            static::assertInstanceOf(WebhookEntity::class, $webhook);
            static::assertSame(0, $webhook->getErrorCount());
            static::assertTrue($webhook->isActive());
        });
    }

    private function createWebhookEventMessage(
        string $webhookEventId,
        string $appId,
        string $webhookId,
        ?string $partitionKey = null,
    ): WebhookEventMessage {
        return new WebhookEventMessage(
            $webhookEventId,
            ['body' => 'payload'],
            $appId,
            $webhookId,
            '6.4',
            'http://test.com',
            's3cr3t',
            Defaults::LANGUAGE_SYSTEM,
            'en-GB',
            partitionKey: $partitionKey,
        );
    }

    private function removePartitionKeyFromSerializedMessage(WebhookEventMessage $message): WebhookEventMessage
    {
        $serialized = serialize($message);
        $serialized = preg_replace_callback(
            '/^O:(\d+):"([^"]+)":(\d+):\{/',
            static fn (array $matches): string => \sprintf(
                'O:%d:"%s":%d:{',
                (int) $matches[1],
                $matches[2],
                (int) $matches[3] - 1
            ),
            $serialized
        );
        static::assertIsString($serialized);
        $serialized = str_replace('s:12:"partitionKey";N;', '', $serialized);

        $legacy = Serialization::assertUnserializedInstanceOf(WebhookEventMessage::class, $serialized);
        static::assertInstanceOf(WebhookEventMessage::class, $legacy);

        return $legacy;
    }

    private function createAppWithWebhook(string $appId, string $webhookId, int $errorCount = 0, bool $active = true): void
    {
        $appRepository = static::getContainer()->get('app.repository');

        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key ' . $appId,
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp' . $appId,
            ],
            'webhooks' => [
                [
                    'id' => $webhookId,
                    'name' => 'hook1',
                    'eventName' => 'order',
                    'url' => 'https://test.com',
                    'errorCount' => $errorCount,
                    'active' => $active,
                ],
            ],
        ]], $this->context);
    }

    private function createOutboxEntry(WebhookEventMessage $message, string $webhookId): void
    {
        $this->webhookOutboxStore->recordOutboxEntry(new OutboxInsert(
            $message->getWebhookEventId(),
            $webhookId,
            Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            serialize($message),
        ));
    }

    private function failWithRetrySubscriber(WorkerMessageFailedEvent $event, ?RetryWebhookMessageFailedSubscriber $subscriber = null): void
    {
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($event, $subscriber): void {
            ($subscriber ?? static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class))->failed($event);
        });
    }
}
