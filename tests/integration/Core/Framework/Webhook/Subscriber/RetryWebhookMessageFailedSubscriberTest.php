<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Subscriber\RetryWebhookMessageFailedSubscriber;
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
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

    private OutboxEventRepository $outboxEventRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->outboxEventRepository = static::getContainer()->get(OutboxEventRepository::class);
    }

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

        static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class)
            ->failed($event);

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

        static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class)
            ->failed($event);

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
            static::getContainer()->get(OutboxEventRepository::class),
            static::getContainer()->get(RelatedWebhooks::class),
            WebhookFailureStrategy::Ignore->value
        );

        $subscriber->failed($event);

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

    public function testWillRetryDoesNotApplyFailureStrategy(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId);
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);
        $this->createOutboxEntry($webhookEventMessage, $webhookId);

        // Simulate handler: markRunning then resetForRetry (as handler does before throwing)
        $this->outboxEventRepository->markRunning($webhookEventId);
        $this->outboxEventRepository->resetForRetry($webhookEventId);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );
        $event->setForRetry();

        static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class)
            ->failed($event);

        // Failure strategy must NOT be applied on retryable failures -- error_count stays 0
        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();
        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(0, $webhook->getErrorCount());
        static::assertTrue($webhook->isActive());
    }

    public function testRetryDoesNotApplyFailureStrategyEvenAtThreshold(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, 9);
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);
        $this->createOutboxEntry($webhookEventMessage, $webhookId);

        // Handler sets RUNNING then resets to QUEUED before throwing — simulate that
        $this->outboxEventRepository->markRunning($webhookEventId);
        $this->outboxEventRepository->resetForRetry($webhookEventId);

        $exception = WebhookException::webhookFailedException($webhookId, new \RuntimeException('timeout'));

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            $exception
        );
        $event->setForRetry();

        static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class)
            ->failed($event);

        // Subscriber must not touch event log on retry — status stays QUEUED (set by handler)
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $status);

        // Error count must NOT change, webhook must NOT be disabled
        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();
        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(9, $webhook->getErrorCount());
        static::assertTrue($webhook->isActive());
    }

    public function testTerminalFailureAtThresholdDisablesWebhook(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, 9);
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);
        $this->createOutboxEntry($webhookEventMessage, $webhookId);

        $this->outboxEventRepository->markRunning($webhookEventId);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );

        static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class)
            ->failed($event);

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
            $this->outboxEventRepository,
            static::getContainer()->get(RelatedWebhooks::class),
            WebhookFailureStrategy::DisableOnThreshold->value,
        );

        Feature::fake(['WEBHOOKS_REWORK'], function () use ($subscriber, $event, $webhookEventId, $webhookId): void {
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

    public function testNonWebhookEventMessageIsIgnored(): void
    {
        $nonWebhookMessage = new \stdClass();

        $event = new WorkerMessageFailedEvent(
            new Envelope($nonWebhookMessage),
            'async',
            new \RuntimeException('some error')
        );

        $webhookEventLogCountBefore = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM webhook_event_log');

        static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class)
            ->failed($event);

        $webhookEventLogCountAfter = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM webhook_event_log');
        static::assertSame($webhookEventLogCountBefore, $webhookEventLogCountAfter);
    }

    public function testTerminalFailureOnInactiveWebhookDoesNotApplyStrategy(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, 5, false);
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);
        $this->createOutboxEntry($webhookEventMessage, $webhookId);

        $this->outboxEventRepository->markRunning($webhookEventId);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
        );

        static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class)
            ->failed($event);

        // Event log marked FAILED
        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $status);

        // Error count must NOT change since webhook is already inactive
        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();
        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(5, $webhook->getErrorCount());
        static::assertFalse($webhook->isActive());
    }

    public function testIgnoreStrategyKeepsWebhookActiveAboveThreshold(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, 15);
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);
        $this->createOutboxEntry($webhookEventMessage, $webhookId);

        $this->outboxEventRepository->markRunning($webhookEventId);

        $event = new WorkerMessageFailedEvent(
            new Envelope($webhookEventMessage),
            'async',
            new ClientException('test', new Request('GET', 'https://test.com'), new Response(502))
        );

        $subscriber = new RetryWebhookMessageFailedSubscriber(
            $this->connection,
            $this->outboxEventRepository,
            static::getContainer()->get(RelatedWebhooks::class),
            WebhookFailureStrategy::Ignore->value
        );

        $subscriber->failed($event);

        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();

        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(16, $webhook->getErrorCount());
        static::assertTrue($webhook->isActive());
    }

    public function testTerminalFailureWithDeletedWebhookDoesNotThrow(): void
    {
        $webhookId = Uuid::randomHex();
        $webhookEventId = Uuid::randomHex();
        $appId = Uuid::randomHex();

        /** @var EntityRepository<WebhookEventLogCollection> $webhookEventLogRepository */
        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');

        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);

        // Create event log directly (no webhook in DB -- simulates deletion after dispatch)
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
            new \RuntimeException('Connection reset')
        );

        // Should not throw even though webhook no longer exists
        static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class)
            ->failed($event);

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), $this->context)
            ->getEntities()
            ->first();
        static::assertNotNull($webhookEventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $webhookEventLog->getDeliveryStatus());
    }

    public function testSequentialTerminalFailuresIncrementErrorCount(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();

        $this->createAppWithWebhook($appId, $webhookId, 0);
        $subscriber = static::getContainer()->get(RetryWebhookMessageFailedSubscriber::class);

        for ($i = 1; $i <= 3; ++$i) {
            $webhookEventId = Uuid::randomHex();
            $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);
            $this->createOutboxEntry($webhookEventMessage, $webhookId);
            $this->outboxEventRepository->markRunning($webhookEventId);

            $event = new WorkerMessageFailedEvent(
                new Envelope($webhookEventMessage),
                'async',
                new ClientException('test', new Request('GET', 'https://test.com'), new Response(500))
            );

            $subscriber->failed($event);
        }

        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), $this->context)->first();

        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(3, $webhook->getErrorCount());
        static::assertTrue($webhook->isActive());
    }

    private function createWebhookEventMessage(string $webhookEventId, string $appId, string $webhookId): WebhookEventMessage
    {
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
        );
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
        $this->outboxEventRepository->ensureOutboxEntry(new OutboxInsert(
            $message->getWebhookEventId(),
            $webhookId,
            Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            serialize($message),
        ));
    }
}
