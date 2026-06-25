<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * End-to-end dispatch coverage for the outbox transport. Two smoke tests run under
 * both `WEBHOOKS_REWORK` ON and OFF via #[DataProvider('flagStates')] to confirm the
 * happy-path and transient-failure contracts hold identically across transports:
 *  - `testAsyncWebhookIsDeliveredAndPublishesConsumerContract`
 *  - `testTransientFailureDoesNotBlockLaterMessagesOnSamePartition`
 *
 * The remaining tests run flag-ON only — they target outbox-specific behaviour
 * (retry-cycle, terminal failure, recovery against stale results, sync inline path,
 * insertion order, multi-webhook fan-out) where the flag-OFF leg either duplicates
 * coverage already provided by the dispatcher's own suite or relies on Messenger
 * wiring (`SendFailedMessageForRetryListener`) that `QueueTestBehaviour::runWorker()`
 * does not provide.
 *
 * Assertions target observable end-state — the outbox (`webhook_event_log`,
 * `webhook_delivery`) and the outgoing HTTP request.
 *
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
        $this->connection->executeStatement('DELETE FROM webhook_stream');
        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM webhook_stream');
        $this->connection->executeStatement('DELETE FROM webhook_delivery');
        $this->connection->executeStatement('DELETE FROM webhook_event_log');
        $this->connection->executeStatement('DELETE FROM webhook');
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function flagStates(): iterable
    {
        yield 'flag off' => [false];
        yield 'flag on' => [true];
    }

    /**
     * Steps:
     * 1. Register two webhooks for the same event.
     * 2. Dispatch the event once.
     *
     * Expected:
     * - Each registered webhook produced its own `webhook_event_log` row.
     */
    public function testMultipleWebhooksForSameEventDispatchMultipleOutboxEntries(): void
    {
        $webhookId1 = Uuid::randomHex();
        $webhookId2 = Uuid::randomHex();

        $this->createWebhook($webhookId1, 'webhook-1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/hook1');
        $this->createWebhook($webhookId2, 'webhook-2', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/hook2');

        $this->appendNewResponse(new Response(200));
        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event): void {
            $manager->dispatch($event);
        });

        $names = $this->connection->fetchFirstColumn(
            'SELECT webhook_name FROM webhook_event_log WHERE webhook_name IN (:names)',
            ['names' => ['webhook-1', 'webhook-2']],
            ['names' => ArrayParameterType::STRING]
        );
        sort($names);
        static::assertSame(['webhook-1', 'webhook-2'], $names, 'Each registered webhook must produce its own outbox entry');
    }

    /**
     * Steps:
     * 1. Register a webhook.
     * 2. Dispatch with `isAdminWorkerEnabled = true` — delivery runs inline inside `dispatch()`.
     *
     * Expected:
     * - No `WebhookEventMessage` was enqueued — sync path executes inline, not via the queue.
     * - `webhook_event_log` row is `SUCCESS`.
     * - HTTP POST fired; `X-Shopware-Event-Id` and `X-Shopware-Sequence` match the outbox row;
     *   `X-Shopware-Attempt` is `"0"` (0-indexed first attempt).
     */
    public function testSyncPathDeliversWithinDispatchAndEmitsConsumerContractHeaders(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: true);
        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event): void {
            $manager->dispatch($event);
        });

        static::assertSame(0, $this->getDispatchedMessageCount(WebhookEventMessage::class), 'Sync path must not enqueue a Messenger message');

        $eventLogs = $this->connection->fetchAllAssociative(
            'SELECT id, sequence, delivery_status FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'test-webhook']
        );
        static::assertCount(1, $eventLogs);
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $eventLogs[0]['delivery_status']);

        $request = $this->getLastRequest();
        static::assertNotNull($request, 'Sync path must make the HTTP request inside dispatch');
        static::assertSame('POST', $request->getMethod());

        $expectedEventId = Uuid::fromBytesToHex((string) $eventLogs[0]['id']);
        $expectedSequence = (string) (int) $eventLogs[0]['sequence'];
        static::assertSame($expectedEventId, $request->getHeaderLine('X-Shopware-Event-Id'));
        static::assertSame($expectedSequence, $request->getHeaderLine('X-Shopware-Sequence'));
        static::assertSame('0', $request->getHeaderLine('X-Shopware-Attempt'), 'First attempt is 0-indexed');
    }

    /**
     * Steps:
     * 1. Register a webhook.
     * 2. Dispatch the event.
     * 3. Run the worker so whichever transport holds the message drains it.
     *
     * Expected:
     * - Exactly one HTTP attempt fires.
     * - `webhook_event_log` row is `SUCCESS`; hot `webhook_delivery` row is gone.
     * - Consumer-contract headers are present and match the outbox row.
     * - JSON body carries `source.sequence` equal to the outbox sequence.
     */
    #[DataProvider('flagStates')]
    public function testAsyncWebhookIsDeliveredAndPublishesConsumerContract(bool $flagActive): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        $this->withFlag($flagActive, function () use ($manager, $event): void {
            // Flag ON with admin_worker=true in the container executes inline; flag OFF
            // requires a worker pass to drain the async transport. A single runWorker()
            // drains whichever receiver holds the message (or is a no-op under flag ON).
            $manager->dispatch($event);
            $this->runWorker();
        });

        static::assertSame(1, $this->getRequestCount(), 'Expected exactly one delivery attempt');

        $request = $this->getLastRequest();
        static::assertNotNull($request, 'Expected an HTTP request to be made');
        static::assertSame('POST', $request->getMethod());

        $eventLog = $this->connection->fetchAssociative(
            'SELECT id, sequence, delivery_status FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'test-webhook']
        );
        static::assertIsArray($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $eventLog['delivery_status']);

        $expectedEventId = Uuid::fromBytesToHex((string) $eventLog['id']);
        $expectedSequence = (string) (int) $eventLog['sequence'];
        static::assertSame($expectedEventId, $request->getHeaderLine('X-Shopware-Event-Id'));
        static::assertSame($expectedSequence, $request->getHeaderLine('X-Shopware-Sequence'));
        static::assertSame('0', $request->getHeaderLine('X-Shopware-Attempt'));

        $body = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertArrayHasKey('source', $body);
        static::assertIsArray($body['source']);
        static::assertArrayHasKey('sequence', $body['source']);
        static::assertSame((int) $eventLog['sequence'], $body['source']['sequence']);

        $deliveryCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_id = :webhookId',
            ['webhookId' => Uuid::fromHexToBytes($webhookId)]
        );
        static::assertSame(0, $deliveryCount, 'Delivery row should be cleaned up after successful delivery');
    }

    /**
     * Steps:
     * 1. Register a webhook with error_count = 7.
     * 2. Dispatch the event.
     * 3. Mid-HTTP, simulate a stalled worker: trigger resetRunningForPartition + markRunning
     *    so a new attempt (execution_count = 2) is recovered before the original 200 returns.
     * 4. Run the worker — the original (now stale) attempt receives its 200.
     *
     * Expected:
     * - webhook_delivery sequence matches the recovered attempt; status RUNNING, execution_count = 2.
     * - webhook_event_log stays RUNNING (not flipped to SUCCESS).
     * - webhook.error_count stays at 7 (not reset).
     */
    public function testStaleSuccessOnRecoveredAttemptIsNoop(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');
        $this->connection->update('webhook', ['error_count' => 7], ['id' => Uuid::fromHexToBytes($webhookId)]);

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event): void {
            $manager->dispatch($event);
        });

        $eventId = $this->fetchOutboxEventId('test-webhook');
        $eventLogBytes = Uuid::fromHexToBytes($eventId);
        $partitionKey = $this->connection->fetchOne(
            'SELECT partition_key FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $eventLogBytes]
        );
        static::assertIsString($partitionKey);

        $outbox = static::getContainer()->get(WebhookOutboxStore::class);
        $secondAttemptSequence = null;
        $mockHandler = static::getContainer()->get(MockHandler::class);
        static::assertInstanceOf(MockHandler::class, $mockHandler);
        $mockHandler->append(function () use ($outbox, $eventId, $partitionKey, &$secondAttemptSequence): Response {
            $outbox->resetRunningForPartition($partitionKey, 0);
            $secondAttempt = $outbox->markRunning($eventId);
            static::assertNotNull($secondAttempt);
            static::assertSame(2, $secondAttempt->executionCount);
            $secondAttemptSequence = $secondAttempt->sequence;

            return new Response(200, [], '{"ok":true}');
        });

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function (): void {
            $this->runWorker();
        });

        static::assertSame(1, $this->getRequestCount());
        static::assertIsInt($secondAttemptSequence);

        $delivery = $this->connection->fetchAssociative(
            'SELECT id, delivery_status, execution_count FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => $eventLogBytes]
        );
        static::assertNotFalse($delivery, 'stale success must not delete the recovered active attempt');
        static::assertSame($secondAttemptSequence, (int) $delivery['id']);
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $delivery['delivery_status']);
        static::assertSame(2, (int) $delivery['execution_count']);

        $eventLogStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => $eventLogBytes]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_RUNNING, $eventLogStatus);

        $errorCount = $this->connection->fetchOne(
            'SELECT error_count FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );
        static::assertSame(7, (int) $errorCount, 'stale success must not reset webhook error_count');
    }

    /**
     * Steps:
     * 1. Register one webhook.
     * 2. Dispatch three events back-to-back.
     * 3. Run the worker.
     *
     * Expected:
     * - Three HTTP attempts fire, one per event.
     * - `X-Shopware-Sequence` is strictly monotonically increasing across the three
     *   requests (insertion order is preserved within the partition).
     */
    public function testMessagesForSameWebhookDeliverInInsertionOrder(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        $this->appendNewResponse(new Response(200));
        $this->appendNewResponse(new Response(200));
        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager): void {
            $manager->dispatch($this->createCustomerBeforeLoginEvent());
            $manager->dispatch($this->createCustomerBeforeLoginEvent());
            $manager->dispatch($this->createCustomerBeforeLoginEvent());

            $this->runWorker();
        });

        static::assertSame(3, $this->getRequestCount(), 'Every dispatched event must reach the endpoint');

        // Insertion order within the partition is strictly monotonic in X-Shopware-Sequence.
        $sequences = [
            (int) $this->getPastRequest(0)->getHeaderLine('X-Shopware-Sequence'),
            (int) $this->getPastRequest(1)->getHeaderLine('X-Shopware-Sequence'),
            (int) $this->getPastRequest(2)->getHeaderLine('X-Shopware-Sequence'),
        ];
        static::assertGreaterThan(0, $sequences[0]);
        static::assertGreaterThan($sequences[0], $sequences[1]);
        static::assertGreaterThan($sequences[1], $sequences[2]);
    }

    /**
     * Steps:
     * 1. Register one webhook.
     * 2. Dispatch three events; endpoint returns 200, 500, 200.
     * 3. Run the worker.
     *
     * Expected:
     * - All three HTTP attempts fire — the middle failure does not stall the partition.
     * - Exactly two `webhook_event_log` rows land at `SUCCESS`; the failed one is parked
     *   for retry (`PENDING_RETRY` under flag ON, `QUEUED` under flag OFF).
     */
    #[DataProvider('flagStates')]
    public function testTransientFailureDoesNotBlockLaterMessagesOnSamePartition(bool $flagActive): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        // The middle attempt fails; the partition must still deliver #1 and #3.
        $this->appendNewResponse(new Response(200));
        $this->appendNewResponse(new Response(500, [], '{"error":"fail"}'));
        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);

        $this->withFlag($flagActive, function () use ($manager): void {
            $manager->dispatch($this->createCustomerBeforeLoginEvent());
            $manager->dispatch($this->createCustomerBeforeLoginEvent());
            $manager->dispatch($this->createCustomerBeforeLoginEvent());

            $this->runWorker();
        });

        static::assertSame(3, $this->getRequestCount(), 'A transient failure must not block subsequent messages on the partition');

        // Two of the three event_logs settle on SUCCESS; the middle one is parked for retry
        // (flag ON → PENDING_RETRY via the outbox; flag OFF → QUEUED via resetForRetry). Both
        // leave webhook_event_log in a non-SUCCESS state, so we assert exactly two successes.
        $successes = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_event_log WHERE webhook_name = :name AND delivery_status = :status',
            ['name' => 'test-webhook', 'status' => WebhookEventLogDefinition::STATUS_SUCCESS]
        );
        static::assertSame(2, $successes);
    }

    /**
     * Steps:
     * 1. Dispatch a webhook event.
     * 2. Worker polls → endpoint returns 500.
     * 3. Mark the retry immediately due.
     * 4. Worker polls again → endpoint returns 200.
     *
     * Expected:
     * - Two HTTP attempts land at the endpoint.
     * - `X-Shopware-Event-Id` is stable across both attempts (idempotency key).
     * - `X-Shopware-Sequence` is stable across both attempts (outbox row id).
     * - `X-Shopware-Attempt` goes `0` → `1`.
     */
    public function testRetryPreservesEventIdAndIncrementsAttemptCounter(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        $this->appendNewResponse(new Response(500, [], '{"error":"fail"}'));
        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event): void {
            $manager->dispatch($event);

            $this->runWorker();

            $this->makeRetryImmediatelyDue('test-webhook');

            $this->runWorker();
        });

        static::assertSame(2, $this->getRequestCount());

        $firstAttempt = $this->getPastRequest(0);
        $retryAttempt = $this->getPastRequest(1);

        // Event id is the idempotency key — stable across retries so consumers can dedupe.
        static::assertSame(
            $firstAttempt->getHeaderLine('X-Shopware-Event-Id'),
            $retryAttempt->getHeaderLine('X-Shopware-Event-Id'),
        );
        // Sequence never changes for a given outbox row.
        static::assertSame(
            $firstAttempt->getHeaderLine('X-Shopware-Sequence'),
            $retryAttempt->getHeaderLine('X-Shopware-Sequence'),
        );
        // Attempt counter is 0-indexed and bumps per retry.
        static::assertSame('0', $firstAttempt->getHeaderLine('X-Shopware-Attempt'));
        static::assertSame('1', $retryAttempt->getHeaderLine('X-Shopware-Attempt'));
    }

    /**
     * Steps:
     * 1. Dispatch a webhook event.
     * 2. Fast-forward the retry budget via 5 × (markRunning + markPendingRetry) on the
     *    repository — `execution_count` now sits at `MAX_RETRIES` (5).
     * 3. Worker polls → endpoint returns 500 → `markRunning` bumps to 6, tripping the
     *    terminal branch in `handleFailure`.
     *
     * Expected:
     * - `webhook_event_log` row is `FAILED`.
     * - Hot `webhook_delivery` row is gone.
     */
    public function testTerminalFailureAfterMaxRetriesMovesRowToFailed(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        $this->appendNewResponse(new Response(500, [], '{"error":"fail"}'));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event): void {
            $manager->dispatch($event);

            // Burn the retry budget without actually delivering: each markRunning / markPendingRetry
            // pair bumps execution_count by 1. After 5 pairs the next markRunning (driven by the
            // worker) will bump to 6 and trip the terminal branch inside handleFailure.
            $eventId = $this->fetchOutboxEventId('test-webhook');
            $stateService = static::getContainer()->get(WebhookOutboxStore::class);
            $past = new \DateTimeImmutable('-1 minute');
            for ($i = 0; $i < 5; ++$i) {
                $entry = $stateService->markRunning($eventId);
                static::assertNotNull($entry);
                $stateService->markPendingRetry($entry, $past, null);
            }

            $this->runWorker();
        });

        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'test-webhook']
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $status, 'Row should transition to FAILED once MAX_RETRIES is exceeded');

        $remaining = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_id = :wid',
            ['wid' => Uuid::fromHexToBytes($webhookId)]
        );
        static::assertSame(0, $remaining, 'Terminal FAILED delivery row must be removed from the hot queue');
    }

    /**
     * Steps:
     * 1. Dispatch a webhook event.
     * 2. Worker polls → endpoint returns 500.
     * 3. Mark the retry immediately due.
     * 4. Worker polls again → endpoint returns 200.
     *
     * Expected:
     * - `webhook_event_log` row is `SUCCESS`.
     * - Hot `webhook_delivery` row is gone.
     */
    public function testFailedDeliveryIsReDeliveredOnNextPoll(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        // First attempt fails, second succeeds.
        $this->appendNewResponse(new Response(500, [], '{"error":"fail"}'));
        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event): void {
            $manager->dispatch($event);

            // First pass: delivery fails → PENDING_RETRY with future next_retry_at.
            $this->runWorker();

            $this->makeRetryImmediatelyDue('test-webhook');

            $this->runWorker();
        });

        $status = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'test-webhook']
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $status);

        $deliveryCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_id = :wid',
            ['wid' => Uuid::fromHexToBytes($webhookId)]
        );
        static::assertSame(0, $deliveryCount, 'Terminal delivery row must be removed from the hot table');
    }

    /**
     * Steps:
     * 1. Register two webhooks bound to two different apps for the same event.
     * 2. Dispatch the event once → two outbox entries, two distinct partitions.
     * 3. Run the worker twice. Endpoint for App A returns 500, App B returns 200.
     *    (Two passes are needed because `MySQLWebhookReceiver::get()` drains a single
     *    partition before yielding control, and `StopWorkerWhenIdleListener` halts the
     *    worker on the first idle round.)
     *
     * Expected:
     * - Two HTTP attempts fire — both URLs are hit, regardless of partition claim order.
     * - App A's `webhook_event_log` is `PENDING_RETRY`; App B's is `SUCCESS`.
     * - `webhook_delivery.partition_key` differs between the two rows — failure on
     *   partition A does not block delivery on partition B.
     * - `webhook_stream` carries two distinct partition rows.
     */
    public function testFanOutAcrossPartitionsDeliversInParallelAndIsolatesFailure(): void
    {
        $appAWebhookId = Uuid::randomHex();
        $appBWebhookId = Uuid::randomHex();

        $this->createWebhook($appAWebhookId, 'app-a-hook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/app-a');
        $this->createWebhook($appBWebhookId, 'app-b-hook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/app-b');

        // Route by URL so the test is independent of partition claim order — `claimNext` ties on
        // the partition_key hash, which is unpredictable.
        $router = static function (RequestInterface $request): Response {
            return str_contains($request->getUri()->getPath(), '/app-a')
                ? new Response(500, [], '{"error":"fail"}')
                : new Response(200);
        };
        $mockHandler = static::getContainer()->get(MockHandler::class);
        static::assertInstanceOf(MockHandler::class, $mockHandler);
        $mockHandler->append($router, $router);

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event): void {
            $manager->dispatch($event);
            $this->runWorker();
            $this->runWorker();
        });

        static::assertSame(2, $this->getRequestCount(), 'Each partition must deliver across the two worker passes');

        $hitPaths = [
            $this->getPastRequest(0)->getUri()->getPath(),
            $this->getPastRequest(1)->getUri()->getPath(),
        ];
        sort($hitPaths);
        static::assertSame(['/app-a', '/app-b'], $hitPaths, 'Each partition must reach its own endpoint');

        $statuses = $this->connection->fetchAllKeyValue(
            'SELECT webhook_name, delivery_status FROM webhook_event_log WHERE webhook_name IN (:names)',
            ['names' => ['app-a-hook', 'app-b-hook']],
            ['names' => ArrayParameterType::STRING],
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $statuses['app-a-hook'], 'Failing partition is parked for retry');
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $statuses['app-b-hook'], 'Healthy partition delivers despite the other partition failing');

        $streamPartitions = (int) $this->connection->fetchOne('SELECT COUNT(DISTINCT partition_key) FROM webhook_stream');
        static::assertSame(2, $streamPartitions, 'Two app-bound webhooks must produce two distinct partitions');
    }

    /**
     * Steps:
     * 1. Register a webhook with `error_count = MAX_ERROR_COUNT - 1` (= 9).
     * 2. Dispatch the event.
     * 3. Burn the retry budget the same way `testTerminalFailureAfterMaxRetriesMovesRowToFailed` does.
     * 4. Worker polls → endpoint returns 500 → terminal branch fires.
     *
     * Expected:
     * - `webhook_event_log` row is `FAILED`.
     * - `webhook.error_count` resets to `0` (the disable-on-threshold strategy zeros it).
     * - `webhook.active` flips to `0` — the webhook is disabled at the threshold.
     */
    public function testTerminalFailureBumpsErrorCountAndDisablesWebhookAtThreshold(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');
        $this->connection->update(
            'webhook',
            ['error_count' => WebhookFailureStrategy::MAX_ERROR_COUNT - 1],
            ['id' => Uuid::fromHexToBytes($webhookId)],
        );

        $this->appendNewResponse(new Response(500, [], '{"error":"fail"}'));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event): void {
            $manager->dispatch($event);

            $eventId = $this->fetchOutboxEventId('test-webhook');
            $stateService = static::getContainer()->get(WebhookOutboxStore::class);
            $past = new \DateTimeImmutable('-1 minute');
            for ($i = 0; $i < 5; ++$i) {
                $entry = $stateService->markRunning($eventId);
                static::assertNotNull($entry);
                $stateService->markPendingRetry($entry, $past, null);
            }

            $this->runWorker();
        });

        $eventLogStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'test-webhook'],
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $eventLogStatus);

        $webhook = $this->connection->fetchAssociative(
            'SELECT active, error_count FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)],
        );
        static::assertIsArray($webhook);
        static::assertSame(0, (int) $webhook['error_count'], 'Disable-on-threshold strategy zeroes error_count');
        static::assertSame(0, (int) $webhook['active'], 'Webhook is disabled once it crosses the threshold');
    }

    /**
     * Steps:
     * 1. Register a webhook with `error_count = 5` (non-zero, sub-threshold).
     * 2. Dispatch the event.
     * 3. Run the worker → endpoint returns 200.
     *
     * Expected:
     * - `webhook_event_log` row is `SUCCESS`.
     * - `webhook.error_count` is reset to `0` — a healthy delivery clears prior transient
     *   failures so a webhook that recovers does not silently disable itself later.
     */
    public function testSuccessfulDeliveryResetsWebhookErrorCount(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');
        $this->connection->update(
            'webhook',
            ['error_count' => 5],
            ['id' => Uuid::fromHexToBytes($webhookId)],
        );

        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event): void {
            $manager->dispatch($event);
            $this->runWorker();
        });

        $eventLogStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'test-webhook'],
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $eventLogStatus);

        $errorCount = (int) $this->connection->fetchOne(
            'SELECT error_count FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)],
        );
        static::assertSame(0, $errorCount, 'Successful delivery must reset error_count so prior transient failures do not accumulate');
    }

    /**
     * Steps:
     * 1. Register a webhook.
     * 2. Endpoint raises `ConnectException` — the request never receives a response.
     * 3. Run the worker.
     *
     * Expected:
     * - One HTTP attempt was made.
     * - `webhook_event_log` row is `PENDING_RETRY` with NULL `response_status_code` —
     *   the no-response failure branch persists empty response data without crashing.
     * - `webhook_delivery` row is still present and parked for retry.
     * - `webhook.error_count` stays at `0` — transient failures only bump after the
     *   retry budget is exhausted.
     */
    public function testNetworkExceptionParksRowForRetryWithoutResponseData(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        $this->appendNewResponse(new ConnectException('Connection refused', new Request('POST', 'https://example.com/webhook')));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event): void {
            $manager->dispatch($event);
            $this->runWorker();
        });

        static::assertSame(1, $this->getRequestCount(), 'A network failure must still count as one delivery attempt');

        $eventLog = $this->connection->fetchAssociative(
            'SELECT delivery_status, response_status_code FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'test-webhook'],
        );
        static::assertIsArray($eventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $eventLog['delivery_status']);
        static::assertNull($eventLog['response_status_code'], 'No-response failures must persist a NULL status code, not crash on persistence');

        $delivery = $this->connection->fetchAssociative(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_id = :wid',
            ['wid' => Uuid::fromHexToBytes($webhookId)],
        );
        static::assertIsArray($delivery, 'Hot delivery row must remain on a transient failure for the next worker pass');
        static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $delivery['delivery_status']);

        $errorCount = (int) $this->connection->fetchOne(
            'SELECT error_count FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)],
        );
        static::assertSame(0, $errorCount, 'A single transient failure must not bump error_count — only terminal failure does');
    }

    /**
     * Steps:
     * 1. Register a webhook (no app binding).
     * 2. Dispatch the event.
     * 3. Run the worker.
     *
     * Expected:
     * - Endpoint received the request.
     * - `webhook_event_log` row is `SUCCESS`.
     * - `webhook_delivery.partition_key` equals the hashed `default` partition fallback —
     *   bare webhooks share one partition rather than failing the dispatch.
     */
    public function testWebhookWithoutAppDeliversOnDefaultPartition(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createBareWebhook($webhookId, 'bare-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/bare');

        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        $expectedPartitionKey = Hasher::hashBinary(WebhookEventMessage::DEFAULT_PARTITION_KEY, 'xxh128');
        // Snapshot the partition key before the worker runs — the success path deletes
        // webhook_delivery once the row settles, so we have to read it mid-cycle.
        $observedPartitionKey = null;

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($manager, $event, &$observedPartitionKey): void {
            $manager->dispatch($event);

            $observedPartitionKey = $this->connection->fetchOne(
                'SELECT d.partition_key FROM webhook_delivery d
                 JOIN webhook_event_log el ON el.id = d.webhook_event_log_id
                 WHERE el.webhook_name = :name',
                ['name' => 'bare-webhook'],
            );

            $this->runWorker();
        });

        static::assertSame(1, $this->getRequestCount());
        static::assertSame($expectedPartitionKey, $observedPartitionKey, 'A webhook without an app must fall back to the default partition');

        $eventLogStatus = $this->connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'bare-webhook'],
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $eventLogStatus);
    }

    /**
     * @param \Closure(): void $closure
     */
    private function withFlag(bool $active, \Closure $closure): void
    {
        $active
            ? Feature::withFeatureEnabled('WEBHOOKS_REWORK', $closure)
            : Feature::withFeatureDisabled('WEBHOOKS_REWORK', $closure);
    }

    /**
     * Returns the webhook_event_log row id (hex) dispatched for the given webhook name.
     * Scoped SELECT isolated to this helper so the rest of the suite stays off the raw
     * schema.
     */
    private function fetchOutboxEventId(string $webhookName): string
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => $webhookName]
        );
        static::assertIsString($id, \sprintf('Expected an outbox entry for webhook "%s"', $webhookName));

        return Uuid::fromBytesToHex($id);
    }

    /**
     * Makes an already scheduled retry immediately pickable by the next worker tick.
     */
    private function makeRetryImmediatelyDue(string $webhookName): void
    {
        $eventId = $this->fetchOutboxEventId($webhookName);
        $this->connection->executeStatement(
            'UPDATE webhook_delivery SET next_retry_at = :retryAt WHERE webhook_event_log_id = :id',
            [
                'retryAt' => (new \DateTimeImmutable('-1 minute'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => Uuid::fromHexToBytes($eventId),
            ]
        );
    }

    private function getWebhookManager(bool $isAdminWorkerEnabled = false): WebhookManager
    {
        $guzzle = static::getContainer()->get('shopware.webhook.guzzle');
        $clock = static::getContainer()->get(ClockInterface::class);
        $webhookClient = new WebhookClient($guzzle, $clock);

        // Construct a fresh WebhookDeliveryService bound to the requested admin_worker flag
        // — the container-wired service reads `%shopware.admin_worker.enable_admin_worker%`
        // (true in the test env), which would otherwise force sync dispatch under flag ON
        // regardless of what this helper's parameter promises.
        $deliveryService = new WebhookDeliveryService(
            $webhookClient,
            static::getContainer()->get(AppPayloadServiceHelper::class),
            static::getContainer()->get(WebhookOutboxStore::class),
            static::getContainer()->get(RetryDelayCalculator::class),
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(WebhookHealthService::class),
            static::getContainer()->get('logger'),
            $isAdminWorkerEnabled,
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
            $isAdminWorkerEnabled,
            $deliveryService,
            static::getContainer()->get(WebhookOutboxStore::class),
        );
    }

    /**
     * Creates a webhook bound to a fresh app — production webhooks are app-backed, so this
     * is the default shape for every test in this file. The acl_role carries empty privileges,
     * which is fine for `CustomerBeforeLoginEvent` (only scalar `email` available data, so
     * `HookableBusinessEvent::isAllowed` short-circuits to true). Each call creates its own
     * app, so two `createWebhook` calls produce two distinct partitions.
     */
    private function createWebhook(string $webhookId, string $name, string $eventName, string $url): void
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
            'id' => Uuid::fromHexToBytes($webhookId),
            'name' => $name,
            'event_name' => $eventName,
            'url' => $url,
            'app_id' => $appId,
            'created_at' => $now,
        ]);
    }

    /**
     * Creates a webhook with no app binding. Reserved for the bare-webhook smoke test —
     * production traffic is app-backed (see {@see self::createWebhook()}).
     */
    private function createBareWebhook(string $webhookId, string $name, string $eventName, string $url): void
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
}
