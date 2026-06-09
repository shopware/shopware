<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Handler;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Shopware\Core\Framework\Webhook\WebhookException;
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
            ($this->webhookEventMessageHandler)($webhookEventMessage);
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

        $connectException = new ConnectException('Connection refused', new Request('POST', 'https://www.shopware.com'));
        $this->appendNewResponse($connectException);

        $this->expectExceptionObject(WebhookException::webhookFailedException($webhookId, $connectException));

        ($this->webhookEventMessageHandler)($webhookEventMessage);
    }

    /**
     * Tests the full success path lifecycle: QUEUED -> RUNNING -> SUCCESS.
     * Verifies that the webhook_event_log retains the audit trail with response data
     * (status code, processing time, response content, reason phrase).
     */
    public function testSuccessPathLifecycleRetainsAuditTrail(): void
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

        $connection = static::getContainer()->get(Connection::class);
        $this->insertWebhookDelivery($connection, $webhookEventId, $webhookId);

        // Verify initial state is QUEUED
        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $webhookEventLog->getDeliveryStatus());

        $this->appendNewResponse(new Response(200, [], '{"success": true}'));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        // After success: verify final state is SUCCESS with full audit trail
        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $webhookEventLog->getDeliveryStatus());

        // Verify response data is persisted
        static::assertSame(200, $webhookEventLog->getResponseStatusCode());
        static::assertSame('OK', $webhookEventLog->getResponseReasonPhrase());
        static::assertNotNull($webhookEventLog->getProcessingTime());
        static::assertGreaterThanOrEqual(0, $webhookEventLog->getProcessingTime());

        // Verify response content contains body and headers
        $responseContent = $webhookEventLog->getResponseContent();
        static::assertIsArray($responseContent);
        static::assertArrayHasKey('body', $responseContent);
        static::assertArrayHasKey('headers', $responseContent);

        // Verify request content is persisted (set during RUNNING transition)
        $requestContent = $webhookEventLog->getRequestContent();
        static::assertIsArray($requestContent);
        static::assertArrayHasKey('headers', $requestContent);
        static::assertArrayHasKey('body', $requestContent);

        // Verify timestamp was set
        static::assertNotNull($webhookEventLog->getTimestamp());

        // Verify webhook_delivery row is cleaned up on success (markSuccess deletes it)
        $deliveryCount = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertSame(0, $deliveryCount, 'webhook_delivery row should be deleted after successful delivery');
    }

    /**
     * Tests the failure path lifecycle: QUEUED -> RUNNING -> QUEUED (resetForRetry).
     * On failure the handler sets the status back to QUEUED (so Messenger retry picks it up)
     * and throws a WebhookException. Response data from the failed attempt is still persisted.
     */
    public function testFailurePathLifecycleResetsToQueued(): void
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

        $connection = static::getContainer()->get(Connection::class);
        $this->insertWebhookDelivery($connection, $webhookEventId, $webhookId);

        // Verify initial state is QUEUED
        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $webhookEventLog->getDeliveryStatus());

        $this->appendNewResponse(new Response(500, [], '{"error": "internal server error"}'));

        $caught = null;
        try {
            ($this->webhookEventMessageHandler)($webhookEventMessage);
        } catch (WebhookException $e) {
            $caught = $e;
        }
        static::assertInstanceOf(WebhookException::class, $caught);
        static::assertSame(WebhookException::APP_WEBHOOK_FAILED, $caught->getErrorCode());

        // After failure: verify status is reset to QUEUED (for Messenger retry)
        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $webhookEventLog->getDeliveryStatus());

        static::assertSame(500, $webhookEventLog->getResponseStatusCode());
        static::assertNotNull($webhookEventLog->getProcessingTime());
        static::assertGreaterThanOrEqual(0, $webhookEventLog->getProcessingTime());

        // Verify response content captures the error body
        $responseContent = $webhookEventLog->getResponseContent();
        static::assertIsArray($responseContent);
        static::assertArrayHasKey('body', $responseContent);
        static::assertArrayHasKey('headers', $responseContent);

        // Verify request content was set during the RUNNING transition
        $requestContent = $webhookEventLog->getRequestContent();
        static::assertIsArray($requestContent);
        static::assertArrayHasKey('headers', $requestContent);
        static::assertArrayHasKey('body', $requestContent);

        // Verify webhook_delivery row is preserved on resetForRetry (stays for next attempt)
        $deliveryStatus = $connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $deliveryStatus, 'webhook_delivery row should be preserved with QUEUED status after failure');
    }

    public function testFailurePathWithUnserializableResponseResetsToQueued(): void
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

        $this->appendNewResponse(new Response(500, [], pack('C*', 0xB1)));

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage): void {
            $caught = null;
            try {
                ($this->webhookEventMessageHandler)($webhookEventMessage);
                static::fail('Malformed failure response should still throw for Messenger retry.');
            } catch (WebhookException $e) {
                $caught = $e;
            }

            static::assertSame(1, $this->getRequestCount());
            static::assertSame(WebhookException::APP_WEBHOOK_FAILED, $caught->getErrorCode());
            static::assertInstanceOf(ServerException::class, $caught->getPrevious());
        });

        $eventLogStatus = $connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $eventLogStatus);

        $deliveryStatus = $connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $deliveryStatus);
    }

    public function testSuccessPathWithUnserializableResponseMarksSuccessWithoutRetry(): void
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

        $this->appendNewResponse(new Response(200, ['X-Bad-Audit-Header' => pack('C*', 0xB1)], '{"ok":true}'));

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage): void {
            ($this->webhookEventMessageHandler)($webhookEventMessage);
        });

        static::assertSame(1, $this->getRequestCount());

        $eventLogStatus = $connection->fetchOne(
            'SELECT delivery_status FROM webhook_event_log WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $eventLogStatus);

        $deliveryExists = $connection->fetchOne(
            'SELECT 1 FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertFalse($deliveryExists);
    }

    /**
     * Tests that on a network error (no response at all), the handler still transitions
     * to QUEUED and preserves the request content from the RUNNING phase, but does not
     * persist response data since no HTTP response was received.
     */
    public function testNetworkErrorLifecycleResetsToQueuedWithoutResponseData(): void
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

        $connection = static::getContainer()->get(Connection::class);
        $this->insertWebhookDelivery($connection, $webhookEventId, $webhookId);

        $this->appendNewResponse(new ConnectException('Connection refused', new Request('POST', 'https://test.com')));

        $caught = null;
        try {
            ($this->webhookEventMessageHandler)($webhookEventMessage);
        } catch (WebhookException $e) {
            // ConnectException has no response, so this falls through to webhookFailedException (not app variant)
            $caught = $e;
        }
        static::assertInstanceOf(WebhookException::class, $caught);
        static::assertSame(WebhookException::WEBHOOK_FAILED, $caught->getErrorCode());

        // After network error: status should be reset to QUEUED
        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $webhookEventLog->getDeliveryStatus());

        // Processing time should still be set
        static::assertNotNull($webhookEventLog->getProcessingTime());

        // No response data since there was no HTTP response
        static::assertNull($webhookEventLog->getResponseStatusCode());
        static::assertNull($webhookEventLog->getResponseReasonPhrase());
        static::assertNull($webhookEventLog->getResponseContent());

        // Request content should still be persisted from the RUNNING phase
        $requestContent = $webhookEventLog->getRequestContent();
        static::assertIsArray($requestContent);
        static::assertArrayHasKey('headers', $requestContent);
        static::assertArrayHasKey('body', $requestContent);

        // Verify webhook_delivery row is preserved on resetForRetry (stays for next attempt)
        $deliveryStatus = $connection->fetchOne(
            'SELECT delivery_status FROM webhook_delivery WHERE webhook_event_log_id = :id',
            ['id' => Uuid::fromHexToBytes($webhookEventId)]
        );
        static::assertSame(WebhookEventLogDefinition::STATUS_QUEUED, $deliveryStatus, 'webhook_delivery row should be preserved with QUEUED status after network error');
    }

    /**
     * After a successful delivery, error_count should be reset to 0 on the webhook
     * and all related webhooks (same event, url, live config).
     */
    public function testSuccessResetsErrorCountOnWebhookAndRelated(): void
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
                    'url' => 'https://test.com',
                    'errorCount' => 5,
                ],
            ],
        ]], Context::createDefaultContext());

        // Create a related webhook (same event + url) via DBAL so it shares the same event/url
        $connection = static::getContainer()->get(Connection::class);
        $connection->insert('webhook', [
            'id' => Uuid::fromHexToBytes($relatedWebhookId),
            'name' => 'hook1-related',
            'event_name' => 'order',
            'url' => 'https://test.com',
            'error_count' => 7,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

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

        $this->insertWebhookDelivery($connection, $webhookEventId, $webhookId);

        $this->appendNewResponse(new Response(200, [], '{"ok": true}'));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        // Verify the primary webhook's error_count is reset to 0
        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(0, $webhook->getErrorCount());

        // Verify the related webhook's error_count is also reset to 0
        $relatedWebhook = $webhookRepository->search(new Criteria([$relatedWebhookId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(WebhookEntity::class, $relatedWebhook);
        static::assertSame(0, $relatedWebhook->getErrorCount());
    }

    /**
     * On failure, the handler should NOT reset error_count -- that is only done on success.
     * The error_count is only incremented by RetryWebhookMessageFailedSubscriber after all retries are exhausted.
     */
    public function testFailureDoesNotResetErrorCount(): void
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
                    'errorCount' => 3,
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

        $this->appendNewResponse(new Response(500, [], '{"error": "fail"}'));

        try {
            ($this->webhookEventMessageHandler)($webhookEventMessage);
        } catch (WebhookException) {
            // expected
        }

        // error_count should remain unchanged -- handler only resets on success
        $webhookRepository = static::getContainer()->get('webhook.repository');
        $webhook = $webhookRepository->search(new Criteria([$webhookId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(WebhookEntity::class, $webhook);
        static::assertSame(3, $webhook->getErrorCount());
    }

    /**
     * Tests that error_count reset is gracefully handled when the webhook is deleted
     * between sending and the reset attempt. The handler should still complete without error
     * and the event log should still record SUCCESS.
     */
    public function testSuccessGracefullyHandlesDeletedWebhookDuringErrorCountReset(): void
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

        // Delete the app (and its webhook) before the handler runs
        $appRepository->delete([['id' => $appId]], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200, [], '{"ok": true}'));

        // Should not throw despite the webhook being deleted
        ($this->webhookEventMessageHandler)($webhookEventMessage);

        // Event log should still record SUCCESS
        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $webhookEventLog->getDeliveryStatus());
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

    public function testLegacyMessageWithoutDeliveryRowStillDeliversHttpRequest(): void
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

        // No insertWebhookDelivery -- simulating a pre-outbox message in the queue.

        $this->appendNewResponse(new Response(200));

        Feature::withFeatureDisabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage, $webhookEventLogRepository, $webhookEventId): void {
            ($this->webhookEventMessageHandler)($webhookEventMessage);

            // Verify the HTTP request was actually sent
            $request = $this->getLastRequest();
            static::assertInstanceOf(RequestInterface::class, $request);

            // Legacy envelopes (no partition key) get no rework headers — dispatch order isn't reliable.
            static::assertFalse($request->hasHeader(WebhookDeliveryService::HEADER_SEQUENCE));
            static::assertFalse($request->hasHeader(WebhookDeliveryService::HEADER_ATTEMPT));

            $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
            static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
            static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $webhookEventLog->getDeliveryStatus());
        });
    }

    public function testLegacyMessageWithoutDeliveryRowDeliversHttp(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createAppWithWebhook($appId, $webhookId);

        // No pre-created event_log or delivery row — recordOutboxEntry creates both as fallback.
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = $this->createWebhookEventMessage($webhookEventId, $appId, $webhookId);

        $this->appendNewResponse(new Response(200));

        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', function () use ($webhookEventMessage, $webhookEventLogRepository, $webhookEventId): void {
            ($this->webhookEventMessageHandler)($webhookEventMessage);

            $request = $this->getLastRequest();
            static::assertInstanceOf(RequestInterface::class, $request);

            $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();
            static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
            static::assertSame(WebhookEventLogDefinition::STATUS_SUCCESS, $webhookEventLog->getDeliveryStatus());
        });
    }

    public function testDeliveryCreatesRowWhenMissingAndDeliversHttp(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createAppWithWebhook($appId, $webhookId);

        // No pre-created event_log or delivery row — recordOutboxEntry creates both as fallback.
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
