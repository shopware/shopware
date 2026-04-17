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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookRequest;
use Shopware\Core\Framework\Webhook\Service\WebhookStateRepository;
use Shopware\Core\Framework\Webhook\WebhookFailureStrategy;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Clock\MockClock;

/**
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

    private OutboxEventRepository&MockObject $outboxEventRepository;

    private RetryDelayCalculator $retryDelayCalculator;

    private CollectingMessageBus $bus;

    private WebhookStateRepository&MockObject $webhookStateRepository;

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
        $this->outboxEventRepository = $this->createMock(OutboxEventRepository::class);
        $this->retryDelayCalculator = new RetryDelayCalculator($this->clock);
        $this->bus = new CollectingMessageBus();
        $this->webhookStateRepository = $this->createMock(WebhookStateRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testProcessDispatchesMessagesToBusWhenAdminWorkerDisabledAndNotForceSynchronous(): void
    {
        $service = $this->createService(isAdminWorkerEnabled: false);

        $msg1 = $this->createMessage();
        $msg2 = $this->createMessage();

        $this->outboxEventRepository->expects($this->never())->method('ensureOutboxEntry');

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
        $this->outboxEventRepository->expects($this->once())->method('ensureOutboxEntry');
        $this->outboxEventRepository->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(executionCount: 1, sequence: 1));

        $this->queueGuzzleResponse(new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'));

        $this->outboxEventRepository->expects($this->once())->method('markSuccess');
        $this->webhookStateRepository->expects($this->once())->method('resetErrorCount');

        $service = $this->createService(isAdminWorkerEnabled: true);
        $service->process([$msg]);

        static::assertCount(0, $this->bus->getMessages());
    }

    public function testProcessDeliversBatchSynchronouslyWhenForceSynchronous(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->outboxEventRepository->expects($this->once())->method('ensureOutboxEntry');
        $this->outboxEventRepository->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(executionCount: 1, sequence: 1));

        $this->queueGuzzleResponse(new Response(200, [], '{"status":"ok"}'));

        $this->outboxEventRepository->expects($this->once())->method('markSuccess');

        $service = $this->createService(isAdminWorkerEnabled: false);
        $service->process([$msg], forceSynchronous: true);

        static::assertCount(0, $this->bus->getMessages());
    }

    public function testDeliverSuccessfulCallsMarkSuccessAndResetsErrorCount(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->outboxEventRepository->expects($this->once())->method('markRunning')
            ->with($msg->getWebhookEventId())
            ->willReturn(new OutboxEntry(executionCount: 1, sequence: 1));

        $this->queueGuzzleResponse(new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'));

        $this->outboxEventRepository->expects($this->once())->method('markSuccess')
            ->with($msg->getWebhookEventId(), static::anything());
        $this->webhookStateRepository->expects($this->once())->method('resetErrorCount')
            ->with($msg->getWebhookId());
        $this->outboxEventRepository->expects($this->never())->method('markPendingRetry');
        $this->outboxEventRepository->expects($this->never())->method('markFailed');

        $service = $this->createService();
        $service->deliver($msg);
    }

    public function testDeliverFailedNonTerminalCallsMarkPendingRetryWithCorrectRetryAt(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->outboxEventRepository->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(executionCount: 2, sequence: 1));

        $this->queueGuzzleResponse(new Response(500, [], '{"error":"fail"}'));

        // executionCount=2 -> RETRY_DELAYS[1] = 30s
        $expectedRetryAt = new \DateTimeImmutable('2026-04-15 12:00:30');
        $this->outboxEventRepository->expects($this->once())->method('markPendingRetry')
            ->with($msg->getWebhookEventId(), $expectedRetryAt, static::anything());
        $this->outboxEventRepository->expects($this->never())->method('markFailed');
        $this->webhookStateRepository->expects($this->never())->method('incrementErrorCount');

        $service = $this->createService();
        $service->deliver($msg);
    }

    public function testDeliverFailedTerminalDisableOnThresholdDeactivatesWebhook(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        // executionCount > MAX_RETRIES (5) -> terminal
        $this->outboxEventRepository->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(executionCount: 6, sequence: 1));

        $this->queueGuzzleResponse(new Response(500, [], '{"error":"fail"}'));

        $this->outboxEventRepository->expects($this->once())->method('markFailed')
            ->with($msg->getWebhookEventId(), static::anything());
        $this->webhookStateRepository->expects($this->once())->method('incrementErrorCount')
            ->with($msg->getWebhookId())
            ->willReturn(10);
        $this->webhookStateRepository->expects($this->once())->method('deactivate')
            ->with($msg->getWebhookId());
        $this->outboxEventRepository->expects($this->never())->method('markPendingRetry');

        $service = $this->createService(failureStrategy: WebhookFailureStrategy::DisableOnThreshold->value);
        $service->deliver($msg);
    }

    public function testDeliverFailedTerminalIgnoreStrategyDoesNotDeactivate(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->outboxEventRepository->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(executionCount: 6, sequence: 1));

        $this->queueGuzzleResponse(new Response(500, [], '{"error":"fail"}'));

        $this->outboxEventRepository->expects($this->once())->method('markFailed');
        $this->webhookStateRepository->expects($this->once())->method('incrementErrorCount')
            ->with($msg->getWebhookId())
            ->willReturn(15);
        $this->webhookStateRepository->expects($this->never())->method('deactivate');

        $service = $this->createService(failureStrategy: WebhookFailureStrategy::Ignore->value);
        $service->deliver($msg);
    }

    public function testDeliverSuccessfulResultPopulatesProcessingTime(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->outboxEventRepository->method('markRunning')
            ->willReturn(new OutboxEntry(executionCount: 1, sequence: 1));

        $this->queueGuzzleResponse(new Response(200, [], '{"ok":true}'));

        $this->outboxEventRepository->expects($this->once())->method('markSuccess')
            ->with(
                $msg->getWebhookEventId(),
                static::callback(function ($response) {
                    static::assertInstanceOf(DeliveryResponse::class, $response);

                    return $response->processingTime >= 0;
                })
            );

        $service = $this->createService();
        $service->deliver($msg);
    }

    public function testDeliverBatchJsonExceptionLogsErrorAndContinues(): void
    {
        $msg1 = $this->createMessage();
        $msg2 = $this->createMessage();

        $webhookRequest1 = $this->createWebhookRequest();
        $webhookRequest2 = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')
            ->willReturnOnConsecutiveCalls($webhookRequest1, $webhookRequest2);

        $this->outboxEventRepository->method('markRunning')
            ->willReturn(new OutboxEntry(executionCount: 1, sequence: 1));

        // Queue two successful Guzzle responses for the batch
        $this->queueGuzzleResponse(new Response(200, [], '{"status":"ok"}'));
        $this->queueGuzzleResponse(new Response(200, [], '{"status":"ok"}'));

        // Simulate JsonException during handleResult for msg1 by making markSuccess throw on first call
        $callCount = 0;
        $this->outboxEventRepository->expects($this->exactly(2))->method('markSuccess')
            ->willReturnCallback(function () use (&$callCount): void {
                ++$callCount;
                if ($callCount === 1) {
                    throw new \JsonException('Malformed UTF-8 characters');
                }
            });

        $this->logger->expects($this->once())->method('error')
            ->with(
                'Webhook delivery result handling failed for event {eventId}',
                static::callback(function (array $context) use ($msg1) {
                    return $context['eventId'] === $msg1->getWebhookEventId()
                        && $context['webhookId'] === $msg1->getWebhookId()
                        && $context['exception'] instanceof \JsonException;
                })
            );

        // msg2 should still be processed despite msg1's failure
        $this->webhookStateRepository->expects($this->once())->method('resetErrorCount')
            ->with($msg2->getWebhookId());

        $service = $this->createService(isAdminWorkerEnabled: true);
        $service->process([$msg1, $msg2]);
    }

    public function testDeliverFailedTerminalBelowThresholdDoesNotDeactivate(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->outboxEventRepository->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(executionCount: 6, sequence: 1));

        $this->queueGuzzleResponse(new Response(500, [], '{"error":"fail"}'));

        $this->outboxEventRepository->expects($this->once())->method('markFailed');
        // Error count below threshold (< 10)
        $this->webhookStateRepository->expects($this->once())->method('incrementErrorCount')
            ->with($msg->getWebhookId())
            ->willReturn(5);
        $this->webhookStateRepository->expects($this->never())->method('deactivate');

        $service = $this->createService(failureStrategy: WebhookFailureStrategy::DisableOnThreshold->value);
        $service->deliver($msg);
    }

    public function testDeliverSwallowsDBALExceptionFromMarkSuccess(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->outboxEventRepository->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(executionCount: 1, sequence: 1));

        $this->queueGuzzleResponse(new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}'));

        $this->outboxEventRepository->expects($this->once())->method('markSuccess')
            ->willThrowException(new DBALInvalidArgumentException('Connection lost'));

        $this->logger->expects($this->once())->method('error')
            ->with(
                'Webhook delivery persistence failed for event {eventId}',
                static::callback(function (array $context) use ($msg) {
                    return $context['eventId'] === $msg->getWebhookEventId()
                        && $context['webhookId'] === $msg->getWebhookId()
                        && $context['exception'] instanceof DBALException;
                })
            );

        $service = $this->createService();
        // Must not throw
        $service->deliver($msg);
    }

    public function testDeliverSwallowsDBALExceptionFromMarkPendingRetry(): void
    {
        $msg = $this->createMessage();
        $webhookRequest = $this->createWebhookRequest();

        $this->appPayloadServiceHelper->method('createWebhookRequest')->willReturn($webhookRequest);
        $this->outboxEventRepository->expects($this->once())->method('markRunning')
            ->willReturn(new OutboxEntry(executionCount: 2, sequence: 1));

        // 500 response → non-terminal failure → markPendingRetry
        $this->queueGuzzleResponse(new Response(500, [], '{"error":"fail"}'));

        $this->outboxEventRepository->expects($this->once())->method('markPendingRetry')
            ->willThrowException(new DBALInvalidArgumentException('Connection lost'));

        $this->logger->expects($this->once())->method('error')
            ->with(
                'Webhook delivery persistence failed for event {eventId}',
                static::callback(function (array $context) use ($msg) {
                    return $context['eventId'] === $msg->getWebhookEventId()
                        && $context['webhookId'] === $msg->getWebhookId()
                        && $context['exception'] instanceof DBALException;
                })
            );

        $service = $this->createService();
        // Must not throw
        $service->deliver($msg);
    }

    private function queueGuzzleResponse(Response $response): void
    {
        $this->guzzleMock->append($response);
    }

    private function createService(
        bool $isAdminWorkerEnabled = false,
        string $failureStrategy = WebhookFailureStrategy::DisableOnThreshold->value,
    ): WebhookDeliveryService {
        return new WebhookDeliveryService(
            $this->webhookClient,
            $this->appPayloadServiceHelper,
            $this->outboxEventRepository,
            $this->retryDelayCalculator,
            $this->bus,
            $this->webhookStateRepository,
            $this->logger,
            $isAdminWorkerEnabled,
            $failureStrategy,
        );
    }

    private function createMessage(?string $webhookEventId = null, ?string $webhookId = null): WebhookEventMessage
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
            webhookHeaders: ['X-Custom' => 'value'],
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
