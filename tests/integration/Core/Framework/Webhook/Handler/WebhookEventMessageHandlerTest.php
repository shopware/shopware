<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Handler;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Shopware\Core\Framework\Webhook\EventLog\WebhookEventLogEntity;
use Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\RelatedWebhooks;
use Shopware\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\Test\Integration\App\GuzzleHistoryCollector;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
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

        $customHeaders = [
            'X-Custom-Header' => 'custom-value',
            'X-Another-Header' => 'another-value',
        ];
        $webhookEventLogRepository = static::getContainer()->get('webhook_event_log.repository');
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = new WebhookEventMessage($webhookEventId, ['body' => 'payload'], $appId, $webhookId, '6.4', 'http://test.com', 's3cr3t', Defaults::LANGUAGE_SYSTEM, 'en-GB', $customHeaders);

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

        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $request = $this->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        $payload = $request->getBody()->getContents();

        static::assertEquals('POST', $request->getMethod());
        static::assertSame('payload', json_decode($payload, true, 512, \JSON_THROW_ON_ERROR)['body']);
        static::assertTrue($request->hasHeader('sw-version'));
        static::assertEquals($request->getHeaderLine('sw-version'), '6.4');
        static::assertEquals($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE), 'en-GB');
        static::assertEquals($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE), Defaults::LANGUAGE_SYSTEM);
        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        $currentSecret = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT `app_secret` FROM `app` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($appId)]
        );
        static::assertIsString($currentSecret);
        static::assertEquals(
            hash_hmac('sha256', $payload, $currentSecret),
            $request->getHeaderLine('shopware-shop-signature')
        );

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();

        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertEquals($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_SUCCESS);

        // validate headers
        static::assertSame('custom-value', $request->getHeaderLine('X-Custom-Header'));
        static::assertSame('another-value', $request->getHeaderLine('X-Another-Header'));
    }

    public function testUsesDedicatedWebhookDeliveryClient(): void
    {
        $webhookDeliveryClient = static::getContainer()->get('shopware.webhook.guzzle');

        static::assertInstanceOf(Client::class, $webhookDeliveryClient);
        static::assertNotSame(static::getContainer()->get('shopware.app_system.guzzle'), $webhookDeliveryClient);

        $clientProperty = new \ReflectionProperty(WebhookEventMessageHandler::class, 'client');
        static::assertSame($webhookDeliveryClient, $clientProperty->getValue($this->webhookEventMessageHandler));
    }

    public function testFollowsPermanentRedirectWithPostPayload(): void
    {
        $webhookEventMessage = $this->createWebhookEventMessage();

        $this->appendNewResponse(new Response(307, ['Location' => '/redirect']));
        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $request = $this->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('POST', $request->getMethod());
        static::assertSame('payload', json_decode($request->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR)['body']);
    }

    public function testSeeOtherRedirectUsesBodyLessGet(): void
    {
        $webhookEventMessage = $this->createWebhookEventMessage();

        $this->appendNewResponse(new Response(303, ['Location' => '/redirect']));
        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $request = $this->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('GET', $request->getMethod());
        static::assertSame('', $request->getBody()->getContents());
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }

    public function testValidatesAndPinsCrossHostAndPortRedirectTarget(): void
    {
        $webhookEventMessage = $this->createWebhookEventMessage();

        $this->appendNewResponse(new Response(308, ['Location' => 'http://redirect.test.com:8080/target']));
        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $historyCollector = static::getContainer()->get(GuzzleHistoryCollector::class);
        static::assertInstanceOf(GuzzleHistoryCollector::class, $historyCollector);
        $history = $historyCollector->getHistory();
        static::assertCount(2, $history);
        static::assertInstanceOf(RequestInterface::class, $history[1]['request']);
        static::assertIsArray($history[1]['options']);
        static::assertIsArray($history[1]['options']['curl']);
        static::assertSame('http://redirect.test.com:8080/target', (string) $history[1]['request']->getUri());
        static::assertSame('POST', $history[1]['request']->getMethod());
        static::assertSame(['test.com:80:93.184.216.34', 'redirect.test.com:8080:93.184.216.34'], $history[1]['options']['curl'][\CURLOPT_RESOLVE]);
    }

    public function testDoesNotForwardMixedCaseCredentialHeadersCrossHost(): void
    {
        $webhookEventMessage = $this->createWebhookEventMessage([
            'authorization' => 'Bearer secret',
            'cOoKiE' => 'session=secret',
        ]);

        $this->appendNewResponse(new Response(308, ['Location' => 'http://redirect.test.com/target']));
        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $request = $this->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertFalse($request->hasHeader('Authorization'));
        static::assertFalse($request->hasHeader('Cookie'));
    }

    public function testRejectsUnsafeRedirectTarget(): void
    {
        $webhookEventMessage = $this->createWebhookEventMessage();
        $this->appendNewResponse(new Response(302, ['Location' => 'http://10.0.0.10/target']));

        try {
            ($this->webhookEventMessageHandler)($webhookEventMessage);
            static::fail('Expected webhook delivery to fail.');
        } catch (WebhookException $exception) {
            static::assertSame(WebhookException::WEBHOOK_FAILED, $exception->getErrorCode());
            static::assertInstanceOf(WebhookException::class, $exception->getPrevious());
            static::assertSame(WebhookException::REDIRECT_TARGET_NOT_ALLOWED, $exception->getPrevious()->getErrorCode());
        }

        static::assertSame(1, $this->getRequestCount());
    }

    public function testDoesNotSendWebhookWhenCurlCannotEnforceResolvedTarget(): void
    {
        $webhookEventMessage = $this->createWebhookEventMessage();
        $webhookEventMessageHandler = new WebhookEventMessageHandler(
            static::getContainer()->get('shopware.app_system.guzzle'),
            static::getContainer()->get('webhook_event_log.repository'),
            static::getContainer()->get(RelatedWebhooks::class),
            static::getContainer()->get(WebhookSigningSecretResolver::class),
            static::getContainer()->get(WebhookTargetValidator::class),
            static fn (): bool => false,
        );

        try {
            $webhookEventMessageHandler($webhookEventMessage);
            static::fail('Expected webhook delivery to fail.');
        } catch (WebhookException $exception) {
            static::assertSame(WebhookException::CURL_NOT_AVAILABLE, $exception->getErrorCode());
        }

        static::assertSame(0, $this->getRequestCount());
    }

    public function testFailsAfterFiveRedirects(): void
    {
        $webhookEventMessage = $this->createWebhookEventMessage();
        foreach ([301, 302, 303, 307, 308, 302] as $statusCode) {
            $this->appendNewResponse(new Response($statusCode, ['Location' => '/redirect']));
        }

        try {
            ($this->webhookEventMessageHandler)($webhookEventMessage);
            static::fail('Expected webhook delivery to fail.');
        } catch (WebhookException $exception) {
            static::assertSame(WebhookException::WEBHOOK_FAILED, $exception->getErrorCode());
            static::assertInstanceOf(WebhookException::class, $exception->getPrevious());
            static::assertSame(WebhookException::MAXIMUM_REDIRECTS_EXCEEDED, $exception->getPrevious()->getErrorCode());
        }

        static::assertSame(6, $this->getRequestCount());
        $historyCollector = static::getContainer()->get(GuzzleHistoryCollector::class);
        static::assertInstanceOf(GuzzleHistoryCollector::class, $historyCollector);
        $history = $historyCollector->getHistory();
        static::assertInstanceOf(RequestInterface::class, $history[1]['request']);
        static::assertSame('GET', $history[1]['request']->getMethod());
        static::assertSame('', $history[1]['request']->getBody()->getContents());
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
        $webhookEventMessage = new WebhookEventMessage($webhookEventId, ['body' => 'payload'], $appId, $webhookId, '6.4', 'http://test.com', 's3cr3t', Defaults::LANGUAGE_SYSTEM, 'en-GB');

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

        $appRepository->delete([['id' => $appId]], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $timestamp = time();
        $request = $this->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        $payload = $request->getBody()->getContents();
        $body = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals('POST', $request->getMethod());
        static::assertEquals($body['body'], 'payload');
        static::assertGreaterThanOrEqual($body['timestamp'], $timestamp);
        static::assertTrue($request->hasHeader('sw-version'));
        static::assertEquals($request->getHeaderLine('sw-version'), '6.4');
        static::assertEquals($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE), 'en-GB');
        static::assertEquals($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE), Defaults::LANGUAGE_SYSTEM);
        static::assertTrue($request->hasHeader('shopware-shop-signature'));
        static::assertEquals(
            hash_hmac('sha256', $payload, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();

        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertEquals($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_SUCCESS);
    }

    /**
     * A webhook queued with the secret captured at queue time, then delivered after the app rotated
     * its secret, must be signed with the app's CURRENT secret — otherwise the receiving app rejects
     * the signature until the message is dropped.
     */
    public function testSignsWithTheCurrentSecretAfterASecretRotation(): void
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
            'appSecret' => 'old-secret',
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
        // The message carries the secret as it was when the webhook was queued.
        $webhookEventMessage = new WebhookEventMessage($webhookEventId, ['body' => 'payload'], $appId, $webhookId, '6.4', 'http://test.com', 'old-secret', Defaults::LANGUAGE_SYSTEM, 'en-GB', [], 'SwagApp');

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

        // Rotate the app secret after the message was queued but before it is delivered.
        static::getContainer()->get(Connection::class)->update(
            'app',
            ['app_secret' => 'new-secret'],
            ['id' => Uuid::fromHexToBytes($appId)]
        );

        $this->appendNewResponse(new Response(200));

        ($this->webhookEventMessageHandler)($webhookEventMessage);

        $request = $this->getLastRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        $payload = $request->getBody()->getContents();

        static::assertSame(
            hash_hmac('sha256', $payload, 'new-secret'),
            $request->getHeaderLine('shopware-shop-signature'),
            'Webhook must be signed with the current app secret, not the one captured when it was queued'
        );
        static::assertNotSame(
            hash_hmac('sha256', $payload, 'old-secret'),
            $request->getHeaderLine('shopware-shop-signature')
        );
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
        $webhookEventMessage = new WebhookEventMessage($webhookEventId, ['body' => 'payload'], $appId, $webhookId, '6.4', 'http://test.com', 's3cr3t', Defaults::LANGUAGE_SYSTEM, 'en-GB');

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

        $this->appendNewResponse(new Response(500, [], '<h1>not json</h1>'));

        $wasThrown = false;

        try {
            ($this->webhookEventMessageHandler)($webhookEventMessage);
        } catch (WebhookException $e) {
            $wasThrown = true;
            static::assertEquals(WebhookException::APP_WEBHOOK_FAILED, $e->getErrorCode());
        }

        static::assertTrue($wasThrown);

        $webhookEventLog = $webhookEventLogRepository->search(new Criteria([$webhookEventId]), Context::createDefaultContext())->first();

        static::assertInstanceOf(WebhookEventLogEntity::class, $webhookEventLog);
        static::assertEquals($webhookEventLog->getDeliveryStatus(), WebhookEventLogDefinition::STATUS_QUEUED);
        static::assertEquals($webhookEventLog->getResponseStatusCode(), 500);
        static::assertEquals($webhookEventLog->getResponseContent(), [
            'headers' => [],
            'body' => '<h1>not json</h1>',
        ]);
    }

    /**
     * @param array<string, string> $headers
     */
    private function createWebhookEventMessage(array $headers = []): WebhookEventMessage
    {
        $webhookEventId = Uuid::randomHex();
        $webhookEventMessage = new WebhookEventMessage($webhookEventId, ['body' => 'payload'], null, Uuid::randomHex(), '6.4', 'http://test.com', null, Defaults::LANGUAGE_SYSTEM, 'en-GB', $headers);

        static::getContainer()->get('webhook_event_log.repository')->create([[
            'id' => $webhookEventId,
            'appName' => 'test',
            'deliveryStatus' => WebhookEventLogDefinition::STATUS_QUEUED,
            'webhookName' => 'hook',
            'eventName' => 'order',
            'appVersion' => '0.0.1',
            'url' => 'http://test.com',
            'serializedWebhookMessage' => serialize($webhookEventMessage),
        ]], Context::createDefaultContext());

        return $webhookEventMessage;
    }
}
