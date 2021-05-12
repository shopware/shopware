<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\Handler;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;

class WebhookEventMessageHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use GuzzleTestClientBehaviour;

    private WebhookEventMessageHandler $webhookEventMessageHandler;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_14363', $this);
        $this->webhookEventMessageHandler = $this->getContainer()->get(WebhookEventMessageHandler::class);
    }

    public function testGetHandledMessages(): void
    {
        /** @var array $subscribedMessages */
        $subscribedMessages = $this->webhookEventMessageHandler::getHandledMessages();

        static::assertCount(1, $subscribedMessages);
        static::assertEquals(WebhookEventMessage::class, $subscribedMessages[0]);
    }

    public function testSendSuccessful(): void
    {
        $webhookId = Uuid::randomHex();
        $appId = Uuid::randomHex();

        $appRepository = $this->getContainer()->get('app.repository');
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
                'writeAccess' => false,
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

        $this->appendNewResponse(new Response(200));

        (($this->webhookEventMessageHandler)(new WebhookEventMessage(['body' => 'payload'], $appId, $webhookId, '6.4', 'https://test.com')));

        $timestamp = time();
        $request = $this->getLastRequest();
        $body = $request->getBody()->getContents();
        $body = json_decode($body);

        static::assertEquals('POST', $request->getMethod());
        static::assertEquals($body->body, 'payload');
        static::assertGreaterThanOrEqual($body->timestamp, $timestamp);
        static::assertTrue($request->hasHeader('sw-version'));
        static::assertEquals($request->getHeaderLine('sw-version'), '6.4');
    }
}
