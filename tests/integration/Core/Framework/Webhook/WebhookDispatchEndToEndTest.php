<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\Service\WebhookStateRepository;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * End-to-end dispatch coverage asserting the functional contract holds identically
 * under `WEBHOOKS_REWORK` ON and OFF. Each test receives the target flag state via
 * #[DataProvider], so the two variants are visible in the method signature and
 * PHPUnit reports them as sibling cases (`#flag off`, `#flag on`).
 *
 * Assertions target observable end-state — the outbox (`webhook_event_log`,
 * `webhook_delivery`) and the outgoing HTTP request.
 *
 * Retry-cycle tests run flag-ON only: the outbox owns the retry loop under that
 * flag and the harness can drive it end-to-end. Flag-OFF retry is Messenger-driven
 * via `SendFailedMessageForRetryListener`, which `QueueTestBehaviour::runWorker()`
 * does not wire into its ad-hoc dispatcher; that path is covered in
 * `RetryWebhookMessageFailedSubscriberTest`.
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
     * 1. Register a webhook for `CustomerBeforeLoginEvent`.
     * 2. Dispatch the event.
     *
     * Expected:
     * - Exactly one `webhook_event_log` row exists for the webhook.
     * - Its `event_name` matches the dispatched event.
     */
    #[DataProvider('flagStates')]
    public function testDispatchWritesOneOutboxEntryWithCorrectMetadata(bool $flagActive): void
    {
        $webhookId = Uuid::randomHex();
        $webhookUrl = 'https://example.com/webhook';

        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, $webhookUrl);
        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        $this->withFlag($flagActive, function () use ($manager, $event): void {
            $manager->dispatch($event);
        });

        $eventLogs = $this->connection->fetchAllAssociative(
            'SELECT id, event_name FROM webhook_event_log WHERE webhook_name = :name',
            ['name' => 'test-webhook']
        );
        static::assertCount(1, $eventLogs, 'Dispatch must persist exactly one outbox entry regardless of transport');
        static::assertSame(CustomerBeforeLoginEvent::EVENT_NAME, $eventLogs[0]['event_name']);
    }

    /**
     * Steps:
     * 1. Dispatch a `CustomerBeforeLoginEvent` with no webhook registered for it.
     *
     * Expected:
     * - No `webhook_event_log` rows are written for the event.
     */
    #[DataProvider('flagStates')]
    public function testNoWebhookRegisteredDispatchesNoOutboxEntry(bool $flagActive): void
    {
        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        $this->withFlag($flagActive, function () use ($manager, $event): void {
            $manager->dispatch($event);
        });

        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_event_log WHERE event_name = :name',
            ['name' => CustomerBeforeLoginEvent::EVENT_NAME]
        );
        static::assertSame(0, $count);
    }

    /**
     * Steps:
     * 1. Register two webhooks for the same event.
     * 2. Dispatch the event once.
     *
     * Expected:
     * - Each registered webhook produced its own `webhook_event_log` row.
     */
    #[DataProvider('flagStates')]
    public function testMultipleWebhooksForSameEventDispatchMultipleOutboxEntries(bool $flagActive): void
    {
        $webhookId1 = Uuid::randomHex();
        $webhookId2 = Uuid::randomHex();

        $this->createWebhook($webhookId1, 'webhook-1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/hook1');
        $this->createWebhook($webhookId2, 'webhook-2', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/hook2');

        $this->appendNewResponse(new Response(200));
        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);
        $event = $this->createCustomerBeforeLoginEvent();

        $this->withFlag($flagActive, function () use ($manager, $event): void {
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
     * - `webhook_event_log` row is `SUCCESS`.
     * - HTTP POST fired; `X-Shopware-Event-Id` and `X-Shopware-Sequence` match the outbox row;
     *   `X-Shopware-Attempt` is `"0"` (0-indexed first attempt).
     */
    #[DataProvider('flagStates')]
    public function testSyncPathDeliversWithinDispatchAndEmitsConsumerContractHeaders(bool $flagActive): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: true);
        $event = $this->createCustomerBeforeLoginEvent();

        $this->withFlag($flagActive, function () use ($manager, $event): void {
            $manager->dispatch($event);
        });

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
     * 1. Register one webhook.
     * 2. Dispatch three events back-to-back.
     * 3. Run the worker.
     *
     * Expected:
     * - Three HTTP attempts fire, one per event.
     * - `X-Shopware-Sequence` is strictly monotonically increasing across the three
     *   requests (insertion order is preserved within the partition).
     */
    #[DataProvider('flagStates')]
    public function testMessagesForSameWebhookDeliverInInsertionOrder(bool $flagActive): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook($webhookId, 'test-webhook', CustomerBeforeLoginEvent::EVENT_NAME, 'https://example.com/webhook');

        $this->appendNewResponse(new Response(200));
        $this->appendNewResponse(new Response(200));
        $this->appendNewResponse(new Response(200));

        $manager = $this->getWebhookManager(isAdminWorkerEnabled: false);

        $this->withFlag($flagActive, function () use ($manager): void {
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
            $stateService = static::getContainer()->get(OutboxEventRepository::class);
            $past = new \DateTimeImmutable('-1 minute');
            for ($i = 0; $i < 5; ++$i) {
                $stateService->markRunning($eventId);
                $stateService->markPendingRetry($eventId, $past);
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
     * @param \Closure(): void $closure
     */
    private function withFlag(bool $active, \Closure $closure): void
    {
        // Pin v6.7.0.0 as the baseline in both legs. Without it, `Feature::fake([])` zeroes
        // every v6.*/FEATURE_* env var — flag-OFF tests would then run in a world where the
        // current released version is also disabled, diverging from production.
        $baseline = ['v6.7.0.0'];

        Feature::fake($active ? [...$baseline, 'WEBHOOKS_REWORK'] : $baseline, $closure);
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
     * Re-queues the delivery for immediate pickup on the next worker tick by reusing the
     * repository's own `markPendingRetry` seam — no schema-level UPDATE needed.
     */
    private function makeRetryImmediatelyDue(string $webhookName): void
    {
        static::getContainer()->get(OutboxEventRepository::class)->markPendingRetry(
            $this->fetchOutboxEventId($webhookName),
            new \DateTimeImmutable('-1 minute'),
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
            static::getContainer()->get(OutboxEventRepository::class),
            static::getContainer()->get(RetryDelayCalculator::class),
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(WebhookStateRepository::class),
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
            static::getContainer()->get(OutboxEventRepository::class),
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
}
