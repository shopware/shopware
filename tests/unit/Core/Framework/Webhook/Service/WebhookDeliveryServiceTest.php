<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\InvalidArgumentException as DBALInvalidArgumentException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Health\EndpointHealth;
use Shopware\Core\Framework\Webhook\Health\EndpointState;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\ErrorClassifier;
use Shopware\Core\Framework\Webhook\Message\HeldDeliveryStamp;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookHealthService;
use Shopware\Core\Framework\Webhook\Service\WebhookRequest;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Clock\MockClock;

/**
 * Locks down the delivery service's result handling on both flag sides: which outbox write
 * (success / retry / failed / paused) follows each HTTP outcome, that flag-on results feed the
 * endpoint-health breaker per attempt while a stale (no longer owned) attempt must not, and that
 * DB errors during result persistence are swallowed so crash recovery can pick the row up. A
 * wrong write here double-fires a webhook or poisons the breaker with results that are not ours.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookDeliveryService::class)]
class WebhookDeliveryServiceTest extends TestCase
{
    private const FIXED_TIMESTAMP = 1713182400; // 2024-04-15T12:00:00Z

    private MockHandler $guzzleMock;

    private WebhookClient $webhookClient;

    private AppPayloadServiceHelper&MockObject $appPayloadServiceHelper;

    private WebhookOutboxStore&MockObject $webhookOutboxStore;

    private RetryDelayCalculator $retryDelayCalculator;

    private CollectingMessageBus $bus;

    private WebhookHealthService&MockObject $webhookHealthService;

    private LoggerInterface&MockObject $logger;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new \DateTimeImmutable('2026-04-15 12:00:00'));

        $this->guzzleMock = new MockHandler();
        $stack = HandlerStack::create($this->guzzleMock);
        $stack->push(new AuthMiddleware('6.7.0', $this->createMock(AppLocaleProvider::class)));
        $this->webhookClient = new WebhookClient(new Client(['handler' => $stack]), $this->clock);

        $this->appPayloadServiceHelper = $this->createMock(AppPayloadServiceHelper::class);
        $this->webhookOutboxStore = $this->createMock(WebhookOutboxStore::class);
        $this->retryDelayCalculator = new RetryDelayCalculator($this->clock);
        $this->bus = new CollectingMessageBus();
        $this->webhookHealthService = $this->createMock(WebhookHealthService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testProcessDispatchesMessagesToBusWhenAdminWorkerDisabledAndNotForceSynchronous(): void
    {
        $service = $this->createService(isAdminWorkerEnabled: false);

        $msg1 = $this->createMessage();
        $msg2 = $this->createMessage();

        $this->webhookOutboxStore->expects($this->never())->method('recordOutboxEntry');

        $service->process([$msg1, $msg2]);

        $envelopes = $this->bus->getMessages();
        static::assertCount(2, $envelopes);
        static::assertSame($msg1, $envelopes[0]->getMessage());
        static::assertSame($msg2, $envelopes[1]->getMessage());
    }

    public function testProcessDeliversBatchSynchronouslyWhenAdminWorkerEnabled(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->webhookOutboxStore->expects($this->once())->method('recordInflightOutboxEntry')
            ->with(static::isInstanceOf(OutboxInsert::class))
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 1, deliveryStatus: 'running'));
        $this->webhookOutboxStore->expects($this->never())->method('markRunning');

        $this->queueGuzzleResponse(new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'));

        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->willReturn(true);
        $this->webhookHealthService->expects($this->once())->method('resetErrorCount');

        $service = $this->createService(isAdminWorkerEnabled: true);
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => $service->process([$msg]));

        static::assertCount(0, $this->bus->getMessages());
    }

    public function testProcessDeliversBatchSynchronouslyWhenForceSynchronous(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->webhookOutboxStore->expects($this->once())->method('recordInflightOutboxEntry')
            ->with(static::isInstanceOf(OutboxInsert::class))
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 1, deliveryStatus: 'running'));
        $this->webhookOutboxStore->expects($this->never())->method('markRunning');

        $this->queueGuzzleResponse(new Response(200, [], '{"status":"ok"}'));

        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->willReturn(true);

        $service = $this->createService(isAdminWorkerEnabled: false);
        $service->process([$msg], forceSynchronous: true);

        static::assertCount(0, $this->bus->getMessages());
    }

    public function testDeliverSuccessfulCallsMarkSuccessAndResetsErrorCount(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->webhookOutboxStore->expects($this->once())->method('markRunning')
            ->with($msg->getWebhookEventId())
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 1, deliveryStatus: 'running'));

        $this->queueGuzzleResponse(new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'));

        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->with(static::isInstanceOf(OutboxEntry::class), static::anything())
            ->willReturn(true);
        $this->webhookHealthService->expects($this->once())->method('resetErrorCount')
            ->with($msg->getWebhookId());
        $this->webhookOutboxStore->expects($this->never())->method('markPendingRetry');
        $this->webhookOutboxStore->expects($this->never())->method('markFailed');

        $service = $this->createService();
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    public function testDeliverFailedNonTerminalSchedulesRetryWithoutRecordingFailure(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->webhookOutboxStore->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 2, deliveryStatus: 'running'));

        $this->queueGuzzleResponse(new Response(500, [], '{"error":"fail"}'));

        $this->webhookHealthService->expects($this->never())->method('recordLegacyFailure');
        $this->webhookOutboxStore->expects($this->once())->method('markPendingRetry')
            ->with(static::isInstanceOf(OutboxEntry::class), static::isInstanceOf(\DateTimeImmutable::class), static::anything())
            ->willReturn(true);
        $this->webhookOutboxStore->expects($this->never())->method('markFailed');

        $service = $this->createService();
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    public function testDeliverTerminalFailureMarksFailedAndRecordsFailure(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->webhookOutboxStore->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 6, deliveryStatus: 'running'));

        $this->queueGuzzleResponse(new Response(500, [], '{"error":"fail"}'));

        $this->webhookOutboxStore->expects($this->once())->method('markFailed')
            ->with(static::isInstanceOf(OutboxEntry::class), static::isInstanceOf(DeliveryResponse::class))
            ->willReturn(true);
        $this->webhookOutboxStore->expects($this->never())->method('markPendingRetry');
        $this->webhookHealthService->expects($this->once())->method('recordLegacyFailure')
            ->with($msg->getWebhookId(), WebhookFailureStrategy::DisableOnThreshold);
        $this->webhookHealthService->expects($this->never())->method('resetErrorCount');

        $service = $this->createService();
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    public function testDeliverSkipsWhenMarkRunningReturnsNull(): void
    {
        $msg = $this->createMessage();

        $this->webhookOutboxStore->expects($this->once())->method('markRunning')
            ->with($msg->getWebhookEventId())
            ->willReturn(null);

        $this->appPayloadServiceHelper->expects($this->never())->method('createWebhookRequest');
        $this->webhookOutboxStore->expects($this->never())->method('markSuccess');
        $this->webhookOutboxStore->expects($this->never())->method('markPendingRetry');
        $this->webhookOutboxStore->expects($this->never())->method('markFailed');
        $this->webhookHealthService->expects($this->never())->method('recordLegacyFailure');
        $this->webhookHealthService->expects($this->never())->method('resetErrorCount');

        $service = $this->createService();
        $service->deliver($msg);
    }

    public function testStaleSuccessDoesNotResetErrorCount(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->webhookOutboxStore->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 1, deliveryStatus: 'running'));

        $this->queueGuzzleResponse(new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'));

        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->with(static::isInstanceOf(OutboxEntry::class), static::anything())
            ->willReturn(false);
        $this->webhookHealthService->expects($this->never())->method('resetErrorCount');
        $this->webhookHealthService->expects($this->never())->method('recordLegacyFailure');
        $this->webhookOutboxStore->expects($this->never())->method('markPendingRetry');
        $this->webhookOutboxStore->expects($this->never())->method('markFailed');

        $service = $this->createService();
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    public function testDeliverBatchToleratesNonUtf8ResponseBody(): void
    {
        $msg1 = $this->createMessage();
        $msg2 = $this->createMessage();

        $webhookRequest1 = $this->createWebhookRequest();
        $webhookRequest2 = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')
            ->willReturnOnConsecutiveCalls($webhookRequest1, $webhookRequest2);

        $this->webhookOutboxStore->method('recordInflightOutboxEntry')
            ->willReturnOnConsecutiveCalls(
                new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 2, deliveryStatus: 'running'),
                new OutboxEntry(webhookEventId: 'stub', sequence: 2, executionCount: 1, deliveryStatus: 'running'),
            );
        $this->webhookOutboxStore->expects($this->never())->method('markRunning');

        $this->queueGuzzleResponse(new Response(500, [], pack('C*', 0xB1)));
        $this->queueGuzzleResponse(new Response(200, [], '{"status":"ok"}'));

        $this->webhookOutboxStore->expects($this->once())->method('markPendingRetry')
            ->with(
                static::isInstanceOf(OutboxEntry::class),
                static::isInstanceOf(\DateTimeImmutable::class),
                static::callback(static fn (DeliveryResponse $r): bool => $r->responseContent === null && $r->responseStatusCode === 500),
            )
            ->willReturn(true);
        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->with(static::isInstanceOf(OutboxEntry::class), static::anything())
            ->willReturn(true);

        $this->logger->expects($this->never())->method('error');

        $this->webhookHealthService->expects($this->never())->method('recordLegacyFailure');
        $this->webhookHealthService->expects($this->once())->method('resetErrorCount')
            ->with($msg2->getWebhookId());

        $service = $this->createService(isAdminWorkerEnabled: true);
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => $service->process([$msg1, $msg2]));
    }

    public function testDeliverBatchPersistenceFailureLogsBatchIndexAndPartitionKey(): void
    {
        $msg1 = $this->createMessage();
        $msg2 = $this->createMessage();

        $this->appPayloadServiceHelper->method('createWebhookRequest')
            ->willReturnOnConsecutiveCalls($this->createWebhookRequest(), $this->createWebhookRequest());

        $this->webhookOutboxStore->method('recordInflightOutboxEntry')
            ->willReturnOnConsecutiveCalls(
                new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 1, deliveryStatus: 'running'),
                new OutboxEntry(webhookEventId: 'stub', sequence: 2, executionCount: 1, deliveryStatus: 'running'),
            );

        $this->queueGuzzleResponse(new Response(200, [], '{"status":"ok"}'));
        $this->queueGuzzleResponse(new Response(200, [], '{"status":"ok"}'));

        $dbalException = new DBALInvalidArgumentException('Connection lost');
        $calls = 0;
        $this->webhookOutboxStore->expects($this->exactly(2))
            ->method('markSuccess')
            ->willReturnCallback(static function () use (&$calls, $dbalException): bool {
                ++$calls;
                if ($calls === 1) {
                    throw $dbalException;
                }

                return true;
            });

        $this->logger->expects($this->once())->method('error')
            ->with(
                'Webhook delivery result handling failed for event {eventId}',
                static::callback(function (array $context) use ($msg1): bool {
                    return $context['eventId'] === $msg1->getWebhookEventId()
                        && $context['webhookId'] === $msg1->getWebhookId()
                        && $context['partitionKey'] === $msg1->getPartitionKey()
                        && $context['batchIndex'] === 0
                        && $context['exception'] instanceof DBALException;
                })
            );

        $this->webhookHealthService->expects($this->once())->method('resetErrorCount')
            ->with($msg2->getWebhookId());

        $service = $this->createService(isAdminWorkerEnabled: true);
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => $service->process([$msg1, $msg2]));
    }

    public function testBuildRequestStripsReservedWebhookHeadersCaseInsensitively(): void
    {
        $msg = $this->createMessage(webhookHeaders: [
            'x-shopware-event-id' => 'spoofed-event',
            'X-Shopware-Sequence' => 'spoofed-sequence',
            'X-SHOPWARE-ATTEMPT' => 'spoofed-attempt',
            'X-Custom' => 'value',
        ]);
        $entry = new OutboxEntry(webhookEventId: $msg->getWebhookEventId(), sequence: 42, executionCount: 3, deliveryStatus: 'running');

        $this->appPayloadServiceHelper->expects($this->once())->method('createWebhookRequest')
            ->with(
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                static::anything(),
                static::callback(static function (array $headers) use ($msg): bool {
                    return $headers[WebhookDeliveryService::HEADER_EVENT_ID] === $msg->getWebhookEventId()
                        && $headers[WebhookDeliveryService::HEADER_SEQUENCE] === '42'
                        && $headers[WebhookDeliveryService::HEADER_ATTEMPT] === '2'
                        && $headers['X-Custom'] === 'value'
                        && !isset($headers['x-shopware-event-id'])
                        && !isset($headers['X-SHOPWARE-ATTEMPT']);
                })
            )
            ->willReturn($this->createWebhookRequest());

        $this->createService()->buildRequest($msg, $entry);
    }

    /**
     * @param 'markSuccess'|'markPendingRetry' $throwingMethod
     */
    #[DataProvider('dbalSwallowCases')]
    public function testDeliverSwallowsDBALAndLeavesRowForCrashRecovery(string $throwingMethod, Response $response, int $executionCount): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->webhookOutboxStore->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: $executionCount, deliveryStatus: 'running'));

        $this->queueGuzzleResponse($response);

        $dbalException = new DBALInvalidArgumentException('Connection lost');
        $this->webhookOutboxStore->expects($this->once())->method($throwingMethod)
            ->willThrowException($dbalException);

        $this->logger->expects($this->once())->method('error')
            ->with(
                'Webhook delivery persistence failed for event {eventId}',
                static::callback(function (array $context) use ($msg): bool {
                    return $context['eventId'] === $msg->getWebhookEventId()
                        && $context['webhookId'] === $msg->getWebhookId()
                        && $context['exception'] instanceof DBALException;
                })
            );

        $service = $this->createService();
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    /**
     * @return iterable<string, array{0: string, 1: Response, 2: int}>
     */
    public static function dbalSwallowCases(): iterable
    {
        yield 'success path' => ['markSuccess', new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'), 1];
        yield 'retry path' => ['markPendingRetry', new Response(500, [], '{"error":"fail"}'), 2];
    }

    public function testHoldDispatchesEachMessageWithHeldStamp(): void
    {
        $service = $this->createService();
        $message = $this->createMessage();

        $this->webhookOutboxStore->expects($this->never())->method('recordInflightOutboxEntry');

        $service->hold([$message]);

        $envelopes = $this->bus->getMessages();
        static::assertCount(1, $envelopes);
        static::assertSame($message, $envelopes[0]->getMessage());
        static::assertNotNull($envelopes[0]->last(HeldDeliveryStamp::class));
    }

    public function testRecordsSuccessToEndpointHealthAfterSuccessfulDelivery(): void
    {
        $msg = $this->createMessage();
        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($this->createWebhookRequest());
        $this->webhookOutboxStore->method('markRunning')
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 1, deliveryStatus: 'running'));
        $this->queueGuzzleResponse(new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'));
        $this->webhookOutboxStore->method('markSuccess')->willReturn(true);

        $endpointHealth = $this->createMock(EndpointHealth::class);
        $endpointHealth->expects($this->once())->method('recordSuccess')->with($msg->getWebhookId());

        $service = $this->createService(endpointHealth: $endpointHealth);
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    public function testRecordsClassifiedFailureToEndpointHealthPerAttempt(): void
    {
        $msg = $this->createMessage();
        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($this->createWebhookRequest());
        // executionCount 2 is below MAX_RETRIES: the legacy counter must not bump, which proves
        // the failure is recorded per attempt, not only on an exhausted delivery.
        $this->webhookOutboxStore->method('markRunning')
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 2, deliveryStatus: 'running'));
        // The attempt still owns its RUNNING row, so health is recorded; the stale case is covered below.
        $this->webhookOutboxStore->method('ownsRunningAttempt')->willReturn(true);
        $this->queueGuzzleResponse(new Response(500, [], '{"error":"fail"}'));
        $this->webhookOutboxStore->method('markPendingRetry')->willReturn(true);
        $this->webhookHealthService->expects($this->never())->method('recordLegacyFailure');

        // A 5xx surfaces as a Guzzle exception, so the classifier sees both the status code and
        // the throwable (status 0 would mean no response at all).
        $errorClassifier = $this->createMock(ErrorClassifier::class);
        $errorClassifier->expects($this->once())->method('classify')->with(500, static::isInstanceOf(\Throwable::class))->willReturn(ErrorClassification::TransientServer);
        $endpointHealth = $this->createMock(EndpointHealth::class);
        $endpointHealth->expects($this->once())->method('recordFailure')
            ->with($msg->getWebhookId(), ErrorClassification::TransientServer, 2)
            ->willReturn(EndpointState::Healthy);

        $service = $this->createService(endpointHealth: $endpointHealth, errorClassifier: $errorClassifier);
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    public function testStaleAttemptFailureDoesNotMutateEndpointHealth(): void
    {
        $msg = $this->createMessage();
        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($this->createWebhookRequest());
        $this->webhookOutboxStore->method('markRunning')
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 1, deliveryStatus: 'running'));
        $this->queueGuzzleResponse(new Response(500, [], '{"error":"fail"}'));
        // The row was reclaimed by crash recovery or dropped by a suspension before this late 5xx
        // arrived: the claim is lost, so the result is no longer ours.
        $this->webhookOutboxStore->method('ownsRunningAttempt')->willReturn(false);

        $errorClassifier = $this->createMock(ErrorClassifier::class);
        $endpointHealth = $this->createMock(EndpointHealth::class);
        // A stale attempt must neither classify nor touch health — recordFailure would otherwise
        // degrade or suspend the endpoint on a result whose row write only no-ops.
        $errorClassifier->expects($this->never())->method('classify');
        $endpointHealth->expects($this->never())->method('recordFailure');
        $this->logger->expects($this->once())->method('warning');

        $service = $this->createService(endpointHealth: $endpointHealth, errorClassifier: $errorClassifier);
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    /**
     * Flag-on row placement: the in-flight row's terminal write follows the failure classification
     * and the state recordFailure returned. Payload-class fails regardless of state; DEGRADED and
     * transient-SUSPENDED re-hold the row for the next cooldown release; non-transient SUSPENDED
     * and DISABLED fail it; HEALTHY rides the normal backoff until the retry budget runs out.
     */
    #[DataProvider('resultPlacementCases')]
    public function testFailedDeliveryRowPlacementFollowsClassificationAndResultingState(
        int $responseStatus,
        ErrorClassification $classification,
        EndpointState $resultingState,
        string $expectedPlacement,
        int $executionCount,
    ): void {
        $msg = $this->createMessage();
        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($this->createWebhookRequest());
        $this->webhookOutboxStore->method('markRunning')
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: $executionCount, deliveryStatus: 'running'));
        $this->webhookOutboxStore->method('ownsRunningAttempt')->willReturn(true);
        $this->queueGuzzleResponse(new Response($responseStatus, [], '{"error":"fail"}'));

        $errorClassifier = $this->createMock(ErrorClassifier::class);
        $errorClassifier->expects($this->once())->method('classify')
            ->with($responseStatus, static::isInstanceOf(\Throwable::class))
            ->willReturn($classification);

        // recordFailure transitions health first; its returned state drives the row placement.
        $endpointHealth = $this->createMock(EndpointHealth::class);
        $endpointHealth->expects($this->once())->method('recordFailure')
            ->with($msg->getWebhookId(), $classification, $executionCount)
            ->willReturn($resultingState);

        foreach (['markPaused', 'markFailed', 'markPendingRetry'] as $placement) {
            if ($placement === $expectedPlacement) {
                continue;
            }
            $this->webhookOutboxStore->expects($this->never())->method($placement);
        }
        $this->webhookOutboxStore->expects($this->once())->method($expectedPlacement)->willReturn(true);

        $service = $this->createService(endpointHealth: $endpointHealth, errorClassifier: $errorClassifier);
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    /**
     * @return iterable<string, array{int, ErrorClassification, EndpointState, string, int}>
     */
    public static function resultPlacementCases(): iterable
    {
        // The sender's fault: fail immediately, never retried, even on a HEALTHY endpoint.
        yield 'payload-class 400 fails the row' => [400, ErrorClassification::NonTransientPayload, EndpointState::Healthy, 'markFailed', 1];
        yield 'transient on resulting DEGRADED re-holds the row' => [500, ErrorClassification::TransientServer, EndpointState::Degraded, 'markPaused', 1];
        yield 'transient on SUSPENDED re-holds the row' => [503, ErrorClassification::TransientServer, EndpointState::Suspended, 'markPaused', 1];
        yield 'non-transient auth on SUSPENDED fails the row' => [401, ErrorClassification::NonTransientAuth, EndpointState::Suspended, 'markFailed', 1];
        yield 'non-transient auth on still-HEALTHY fails the row terminally' => [401, ErrorClassification::NonTransientAuth, EndpointState::Healthy, 'markFailed', 1];
        yield '410 on still-DEGRADED fails the row terminally' => [410, ErrorClassification::NonTransientEndpoint, EndpointState::Degraded, 'markFailed', 1];
        yield 'any failure on DISABLED fails the row' => [500, ErrorClassification::TransientServer, EndpointState::Disabled, 'markFailed', 1];
        yield 'transient on HEALTHY retries with backoff' => [500, ErrorClassification::TransientServer, EndpointState::Healthy, 'markPendingRetry', 1];
        yield 'transient on HEALTHY past the retry budget fails the row' => [500, ErrorClassification::TransientServer, EndpointState::Healthy, 'markFailed', 6];
    }

    public function testRateLimitRetryAfterReachesTheBackoffCalculatorCaseInsensitively(): void
    {
        $msg = $this->createMessage();
        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($this->createWebhookRequest());
        $this->webhookOutboxStore->method('markRunning')
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 1, deliveryStatus: 'running'));
        $this->webhookOutboxStore->method('ownsRunningAttempt')->willReturn(true);
        // Lowercase header name: the lookup must be case-insensitive (RFC 9110 field names).
        $this->queueGuzzleResponse(new Response(429, ['retry-after' => '120'], '{"error":"slow down"}'));

        $errorClassifier = $this->createMock(ErrorClassifier::class);
        $errorClassifier->method('classify')->willReturn(ErrorClassification::TransientRateLimit);
        $endpointHealth = $this->createMock(EndpointHealth::class);
        $endpointHealth->method('recordFailure')->willReturn(EndpointState::Healthy);

        // Real calculator on a fixed clock: an honoured Retry-After lands at now+120s, the
        // schedule tier for attempt 1 at now+5s — the assertion only passes if the header arrived.
        $this->webhookOutboxStore->expects($this->once())->method('markPendingRetry')
            ->with(static::isInstanceOf(OutboxEntry::class), $this->clock->now()->modify('+120 seconds'), static::anything())
            ->willReturn(true);

        $service = $this->createService(endpointHealth: $endpointHealth, errorClassifier: $errorClassifier);
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    public function testRetryAfterIsIgnoredForNonRateLimitFailures(): void
    {
        $msg = $this->createMessage();
        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($this->createWebhookRequest());
        $this->webhookOutboxStore->method('markRunning')
            ->willReturn(new OutboxEntry(webhookEventId: 'stub', sequence: 1, executionCount: 1, deliveryStatus: 'running'));
        $this->webhookOutboxStore->method('ownsRunningAttempt')->willReturn(true);
        // A 503 carrying Retry-After: only a 429 (TransientRateLimit) may honour that header.
        $this->queueGuzzleResponse(new Response(503, ['Retry-After' => '120'], '{"error":"maintenance"}'));

        $errorClassifier = $this->createMock(ErrorClassifier::class);
        $errorClassifier->method('classify')->willReturn(ErrorClassification::TransientServer);
        $endpointHealth = $this->createMock(EndpointHealth::class);
        $endpointHealth->method('recordFailure')->willReturn(EndpointState::Healthy);

        // The schedule tier for attempt 1 (+5s), not the header's +120s.
        $this->webhookOutboxStore->expects($this->once())->method('markPendingRetry')
            ->with(static::isInstanceOf(OutboxEntry::class), $this->clock->now()->modify('+5 seconds'), static::anything())
            ->willReturn(true);

        $service = $this->createService(endpointHealth: $endpointHealth, errorClassifier: $errorClassifier);
        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $service->deliver($msg));
    }

    private function queueGuzzleResponse(Response $response): void
    {
        $this->guzzleMock->append($response);
    }

    private function createService(
        bool $isAdminWorkerEnabled = false,
        string $failureStrategy = WebhookFailureStrategy::DisableOnThreshold->value,
        ?EndpointHealth $endpointHealth = null,
        ?ErrorClassifier $errorClassifier = null,
    ): WebhookDeliveryService {
        return new WebhookDeliveryService(
            $this->webhookClient,
            $this->appPayloadServiceHelper,
            $this->webhookOutboxStore,
            $this->retryDelayCalculator,
            $this->bus,
            $this->webhookHealthService,
            $this->logger,
            $endpointHealth ?? $this->createMock(EndpointHealth::class),
            $errorClassifier ?? $this->createMock(ErrorClassifier::class),
            $isAdminWorkerEnabled,
            $failureStrategy,
        );
    }

    /**
     * @param array<string, string> $webhookHeaders
     */
    private function createMessage(?string $webhookEventId = null, ?string $webhookId = null, array $webhookHeaders = ['X-Custom' => 'value']): WebhookEventMessage
    {
        return new WebhookEventMessage(
            webhookEventId: $webhookEventId ?? Uuid::randomHex(),
            payload: ['data' => 'test-payload'],
            appId: Uuid::randomHex(),
            webhookId: $webhookId ?? Uuid::randomHex(),
            shopwareVersion: '6.7.0',
            url: 'https://example.com/webhook',
            secret: 'test-secret',
            languageId: Uuid::randomHex(),
            userLocale: 'en-GB',
            webhookHeaders: $webhookHeaders,
            partitionKey: 'test-partition',
        );
    }

    private function createWebhookRequest(): WebhookRequest
    {
        $body = json_encode(['data' => 'test-payload', 'timestamp' => self::FIXED_TIMESTAMP], \JSON_THROW_ON_ERROR);

        return new WebhookRequest(
            request: new Request('POST', 'https://example.com/webhook', ['Content-Type' => 'application/json'], $body),
            headers: ['Content-Type' => 'application/json'],
            body: $body,
            timestamp: self::FIXED_TIMESTAMP,
            options: ['connect_timeout' => 10, 'timeout' => 20],
        );
    }
}
