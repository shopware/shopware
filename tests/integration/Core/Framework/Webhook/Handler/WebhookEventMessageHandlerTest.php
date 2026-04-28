<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Handler;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogEntity;
use Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class WebhookEventMessageHandlerTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use IntegrationTestBehaviour;

    private WebhookEventMessageHandler $webhookEventMessageHandler;

    protected function setUp(): void
    {
        $this->webhookEventMessageHandler = static::getContainer()->get(WebhookEventMessageHandler::class);
        static::getContainer()->get(SourceResolver::class)->reset();
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testSendSuccessful(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();

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
        ]], Context::createDefaultContext());

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createLegacySerializedMessageWithoutPartitionKey($webhookEventId, $appId, $webhookId);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], Context::createDefaultContext());

        $this->insertWebhookDelivery(static::getContainer()->get(Connection::class), $webhookEventId, $webhookId);

        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $timestamp = time();
        $request = $this->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        $payload = $request->getBody()->getContents();
        $body = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('POST', $request->getMethod());
        static::assertSame($body['body'], 'payload');
        static::assertGreaterThanOrEqual($body['timestamp'], $timestamp);
        static::assertTrue($request->hasHeader('sw-version'));
        static::assertSame($request->getHeaderLine('sw-version'), '6.4');
        static::assertSame($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE), 'en-GB');
        static::assertSame($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE), Defaults::LANGUAGE_SYSTEM);
        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        static::assertSame(
            hash_hmac('sha256', $payload, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();

        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertSame($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_SUCCESS);
        // Legacy envelopes (no partition key) get no rework headers — dispatch order isn't reliable.
        static::assertFalse($request->hasHeader('X-Shopware-Event-Id'));
        static::assertFalse($request->hasHeader('X-Shopware-Sequence'));
        static::assertFalse($request->hasHeader('X-Shopware-Attempt'));

        $requestContent = $webhookEventLog->getRequestContent();
        static::assertIsArray($requestContent);
        static::assertSame($payload, $requestContent['body']);
        $headers = $requestContent['headers'] ?? [];
        static::assertSame('application/json', $headers['Content-Type']);
        static::assertSame('6.4', $headers['sw-version']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $headers[AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE]);
        static::assertSame('en-GB', $headers[AuthMiddleware::SHOPWARE_USER_LANGUAGE]);
        static::assertArrayNotHasKey('X-Shopware-Event-Id', $headers);
        static::assertArrayNotHasKey('X-Shopware-Sequence', $headers);
        static::assertArrayNotHasKey('X-Shopware-Attempt', $headers);
    }

    /**
     * If the app gets deleted between the time the message was generated and the message was again handled, the handling should not fail
     * this especially affects `app.deleted` events
     */
    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testCanStillSendAfterWebhookIsDeleted(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();

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
        ]], Context::createDefaultContext());

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createLegacySerializedMessageWithoutPartitionKey($webhookEventId, $appId, $webhookId);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], Context::createDefaultContext());

        $this->insertWebhookDelivery(static::getContainer()->get(Connection::class), $webhookEventId, $webhookId);

        $appRepository->delete([['id' => $appId]], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $timestamp = time();
        $request = $this->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        $payload = $request->getBody()->getContents();
        $body = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('POST', $request->getMethod());
        static::assertSame($body['body'], 'payload');
        static::assertGreaterThanOrEqual($body['timestamp'], $timestamp);
        static::assertTrue($request->hasHeader('sw-version'));
        static::assertSame($request->getHeaderLine('sw-version'), '6.4');
        static::assertSame($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE), 'en-GB');
        static::assertSame($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE), Defaults::LANGUAGE_SYSTEM);
        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        static::assertSame(
            hash_hmac('sha256', $payload, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();

        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertSame($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_SUCCESS);
    }

    /**
     * If there are issues in the message delivery it might be that the webhook event log is deleted between the time the message was generated and the message was again handled
     * the webhook should still be send
     */
    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testCanStillSendAfterWebhookEventLogIsDeleted(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();

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
        ]], Context::createDefaultContext());

        $webhookEventId = Uuid::randomHex();
        $customHeaders = [
            'X-Custom-Header' => 'custom-value',
            'X-Another-Header' => 'another-value',
        ];
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId, $customHeaders);

        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $timestamp = time();
        $request = $this->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        $payload = $request->getBody()->getContents();
        $body = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('POST', $request->getMethod());
        static::assertSame($body['body'], 'payload');
        static::assertGreaterThanOrEqual($body['timestamp'], $timestamp);
        static::assertTrue($request->hasHeader('sw-version'));
        static::assertSame($request->getHeaderLine('sw-version'), '6.4');
        static::assertSame($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE), 'en-GB');
        static::assertSame($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE), Defaults::LANGUAGE_SYSTEM);
        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        static::assertSame(
            hash_hmac('sha256', $payload, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );
        // Verify custom webhook headers are sent
        static::assertSame('custom-value', $request->getHeaderLine('X-Custom-Header'));
        static::assertSame('another-value', $request->getHeaderLine('X-Another-Header'));
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testNonJsonErrorResponse(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();

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
        ]], Context::createDefaultContext());

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
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
        ]], Context::createDefaultContext());

        $this->insertWebhookDelivery(static::getContainer()->get(Connection::class), $webhookEventId, $webhookId);

        $this->appendNewResponse(new Response(500, [], '<h1>not json</h1>'));

        $caught = null;
        try {
            $this->invokeWithWebhookReworkDisabled($webhookEventMessage);
        } catch (WebhookException $e) {
            $caught = $e;
        }
        static::assertInstanceOf(WebhookException::class, $caught);
        static::assertSame(WebhookException::APP_WEBHOOK_FAILED, $caught->getErrorCode());

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();

        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertSame($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_QUEUED);
        static::assertSame($webhookEventLog->getResponseStatusCode(), 500);
        static::assertEquals($webhookEventLog->getResponseContent(), [
            'headers' => [],
            'body' => '<h1>not json</h1>',
        ]);
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testNetworkErrorThrowsWebhookFailed(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();

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
        ]], Context::createDefaultContext());

        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);

        $this->appendNewResponse(new ConnectException('Connection refused', new Request('POST', 'https://test.com')));

        $this->expectException(WebhookException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->invokeWithWebhookReworkDisabled($webhookEventMessage);
    }

    public function testDeliveryFailureSchedulesPendingRetry(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createAppWithWebhook($appId, $webhookId);

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId, partitionKey: $appId);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://example.com/hook',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], Context::createDefaultContext());

        $connection = static::getContainer()->get(Connection::class);
        $this->insertWebhookDelivery($connection, $webhookEventId, $webhookId);

        $this->appendNewResponse(new Response(500, [], '{"error": "internal server error"}'));

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage, $connection, $webhookEventId, $webhookEventLogRepository): void {
            ($this->webhookEventMessageHandler)($webhookEventMessage);

            $delivery = $connection->fetchAssociative(
                'SELECT delivery_status, next_retry_at FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => Uuid::fromHexToBytes($webhookEventId)]
            );
            static::assertIsArray($delivery);
            static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $delivery['delivery_status']);
            static::assertNotNull($delivery['next_retry_at'], 'next_retry_at must be set for pending_retry');

            $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
            static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
            static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $webhookEventLog->getDeliveryStatus());
        });
    }

    public function testDeliveryMarksTerminalFailureAtMaxRetries(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createAppWithWebhook($appId, $webhookId);

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId, partitionKey: $appId);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://example.com/hook',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], Context::createDefaultContext());

        $connection = static::getContainer()->get(Connection::class);
        $this->insertWebhookDelivery($connection, $webhookEventId, $webhookId);

        // Simulate that this delivery has already been attempted 5 times (markRunning will bump to 6, exceeding MAX_RETRIES)
        $connection->executeStatement(
            'UPDATE webhook_delivery SET execution_count = 5 WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );

        $this->appendNewResponse(new Response(500, [], '{"error": "still failing"}'));

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage, $connection, $webhookEventId, $webhookEventLogRepository): void {
            ($this->webhookEventMessageHandler)($webhookEventMessage);

            $deliveryCount = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => Uuid::fromHexToBytes($webhookEventId)]
            );
            static::assertSame(0, $deliveryCount, 'webhook_delivery row should be deleted after terminal failure');

            $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
            static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
            static::assertSame(WebhookEventLogDefinition::STATUS_FAILED, $webhookEventLog->getDeliveryStatus());
        });
    }

    public function testDeliverySuccessResetsPerWebhookErrorCount(): void
    {
        $webhookId = Uuid::randomHex();
        $relatedWebhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();

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
                    'url' => 'https://example.com/hook',
                    'errorCount' => 5,
                ],
            ],
        ]], Context::createDefaultContext());

        $connection = static::getContainer()->get(Connection::class);
        $connection->insert('webhook', [
            'id' => Uuid::fromHexToBytes($relatedWebhookId),
            'name' => 'hook1-related',
            'event_name' => 'order',
            'url' => 'https://example.com/hook',
            'error_count' => 7,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId, partitionKey: $appId);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://example.com/hook',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], Context::createDefaultContext());

        $this->insertWebhookDelivery($connection, $webhookEventId, $webhookId);

        $this->appendNewResponse(new Response(200, [], '{"ok": true}'));

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage, $connection, $webhookId, $relatedWebhookId): void {
            ($this->webhookEventMessageHandler)($webhookEventMessage);

            $errorCount = (int) $connection->fetchOne(
                'SELECT error_count FROM webhook WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($webhookId)]
            );
            static::assertSame(0, $errorCount, 'Primary webhook error_count should be reset to 0');

            // Related webhooks (same event+URL) also have their error_count reset — matches trunk behavior via RelatedWebhooks
            $relatedErrorCount = (int) $connection->fetchOne(
                'SELECT error_count FROM webhook WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($relatedWebhookId)]
            );
            static::assertSame(0, $relatedErrorCount, 'Related webhook error_count should also be reset (RelatedWebhooks behavior)');
        });
    }

    public function testDeliveryFailureDoesNotThrow(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createAppWithWebhook($appId, $webhookId);

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId, partitionKey: $appId);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://example.com/hook',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], Context::createDefaultContext());

        $connection = static::getContainer()->get(Connection::class);
        $this->insertWebhookDelivery($connection, $webhookEventId, $webhookId);

        $this->appendNewResponse(new ConnectException('Connection refused', new Request('POST', 'https://example.com/hook')));

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage, $webhookEventLogRepository, $webhookEventId): void {
            ($this->webhookEventMessageHandler)($webhookEventMessage);

            $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
            static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
            static::assertSame(WebhookEventLogDefinition::STATUS_PENDING_RETRY, $webhookEventLog->getDeliveryStatus());
        });
    }

    public function testLegacyPathThrowsOnFailure(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createAppWithWebhook($appId, $webhookId);

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
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
        ]], Context::createDefaultContext());

        $connection = static::getContainer()->get(Connection::class);
        $this->insertWebhookDelivery($connection, $webhookEventId, $webhookId);

        $this->appendNewResponse(new Response(500, [], '{"error": "fail"}'));

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage): void {
            $this->expectException(WebhookException::class);
            ($this->webhookEventMessageHandler)($webhookEventMessage);
        });
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function deliveryWithoutPreexistingRowProvider(): iterable
    {
        yield 'flag-off legacy serialized message backfills delivery' => [false, true];
        yield 'flag-on new-shape message creates delivery via ensureOutboxEntry' => [true, false];
    }

    #[DataProvider('deliveryWithoutPreexistingRowProvider')]
    public function testDeliversHttpAndReachesSuccessWithoutPreexistingDeliveryRow(bool $reworkEnabled, bool $useLegacySerializedMessage): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createAppWithWebhook($appId, $webhookId);

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $useLegacySerializedMessage
            ? $this->createLegacySerializedMessageWithoutPartitionKey($webhookEventId, $appId, $webhookId)
            : $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);

        if ($useLegacySerializedMessage) {
            // Legacy path: pre-create event_log without delivery row to simulate pre-outbox queued message.
            $webhookEventLogRepository->create([[
                'id' => $webhookEventId,
                'appName' => 'SwagApp',
                'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
                'webhookName' => 'hook1',
                'eventName' => 'order',
                'appVersion' => '0.0.1',
                'url' => 'https://test.com',
                'serializedWebhookMessage' => serialize($webhookEventMessage),
            ]], Context::createDefaultContext());
        }

        $this->appendNewResponse(new Response(200));

        $invoke = function () use ($webhookEventMessage, $webhookEventLogRepository, $webhookEventId, $useLegacySerializedMessage): void {
            ($this->webhookEventMessageHandler)($webhookEventMessage);

            $request = $this->getLastRequest();
            static::assertInstanceOf(RequestInterface::class, $request);

            if ($useLegacySerializedMessage) {
                // Legacy envelopes (no partition key) carry no rework headers, even after backfill —
                // the backfilled webhook_delivery row has no dispatch-order sequence to surface.
                static::assertFalse($request->hasHeader(WebhookDeliveryService::HEADER_SEQUENCE));
                static::assertFalse($request->hasHeader(WebhookDeliveryService::HEADER_ATTEMPT));
                static::assertFalse($request->hasHeader(WebhookDeliveryService::HEADER_EVENT_ID));
            }

            $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
            static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
            static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $webhookEventLog->getDeliveryStatus());
        };

        if ($reworkEnabled) {
            Feature::withFeatureEnabled('WEBHOOKS_REWORK', $invoke);
        } else {
            Feature::withFeatureDisabled('WEBHOOKS_REWORK', $invoke);
        }
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testDeliverySucceedsWhenWebhookRowIsDeleted(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createAppWithWebhook($appId, $webhookId);

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createLegacySerializedMessageWithoutPartitionKey($webhookEventId, $appId, $webhookId);

        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'https://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], Context::createDefaultContext());

        $connection = static::getContainer()->get(Connection::class);
        $connection->delete('webhook', ['id' => Uuid::fromHexToBytes($webhookId)]);

        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $request = $this->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $webhookEventLog->getDeliveryStatus());
    }

    public function testFlagOffHandlerDoesNotRedeliverAfterSuccessForNewShapeMessage(): void
    {
        // Flag-OFF legacy path: `hasDeliveryRow === false` is not a safe "legacy message"
        // discriminator because markSuccess also deletes the delivery row. A Messenger
        // redelivery would then fall through to an HTTP send without sequence headers.
        // The correct discriminator is whether the message has an explicit partition key. Flag-ON delivery
        // (WebhookDeliveryService::deliver) has no fallthrough branch.
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createAppWithWebhook($appId, $webhookId);

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId, partitionKey: $appId);

        // Simulate a message whose first delivery already succeeded: event_log in SUCCESS,
        // webhook_delivery absent. hasDeliveryRow would return false — the fingerprint that
        // used to be misread as "legacy, deliver again".
        $webhookEventLogRepository->create([[
            'id' => $webhookEventId,
            'appName' => 'SwagApp',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_SUCCESS,
            'webhookName' => 'hook1',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'http://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], Context::createDefaultContext());

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage): void {
            ($this->webhookEventMessageHandler)($webhookEventMessage);

            static::assertSame(0, $this->getRequestCount(), 'new-shape message must not be re-delivered after successful completion');
        });
    }

    public function testDeliveryCreatesRowWhenMissingAndDeliversHttp(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createAppWithWebhook($appId, $webhookId);

        // No pre-created event_log or delivery row — ensureOutboxEntry creates both as fallback.
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId, partitionKey: $appId);

        $connection = static::getContainer()->get(Connection::class);
        $this->appendNewResponse(new Response(200, [], '{"ok":true}'));

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage, $connection, $webhookEventId, $webhookEventLogRepository): void {
            ($this->webhookEventMessageHandler)($webhookEventMessage);

            $request = $this->getLastRequest();
            static::assertInstanceOf(RequestInterface::class, $request);

            $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
            static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
            static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $webhookEventLog->getDeliveryStatus());

            $deliveryCount = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
                ['id' => Uuid::fromHexToBytes($webhookEventId)]
            );
            static::assertSame(0, $deliveryCount, 'webhook_delivery row should be deleted after successful delivery');
        });
    }

    /**
     * @param array<string, string> $webhookHeaders
     */
    private function createWebhookEventMessage(
        string $webhookEventId,
        string $appId,
        string $webhookId,
        array $webhookHeaders = [],
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
            $webhookHeaders,
            $partitionKey,
        );
    }

    private function createLegacySerializedMessageWithoutPartitionKey(string $webhookEventId, string $appId, string $webhookId): WebhookEventMessage
    {
        $message = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);
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

    private function insertWebhookDelivery(Connection $connection, string $webhookEventId, string $webhookId): void
    {
        $connection->insert('webhook_delivery', [
            'webhook_event_log_id' => Uuid::fromHexToBytes($webhookEventId),
            'webhook_id' => Uuid::fromHexToBytes($webhookId),
            'partition_key' => Uuid::fromHexToBytes(Uuid::randomHex()),
            'delivery_status' => WebhookEventLogDefinition::STATUS_QUEUED,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function invokeWithWebhookReworkDisabled(WebhookEventMessage $message): void
    {
        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($message): void {
            ($this->webhookEventMessageHandler)($message);
        });
    }

    private function createAppWithWebhook(string $appId, string $webhookId): void
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
        ]], Context::createDefaultContext());
    }
}
