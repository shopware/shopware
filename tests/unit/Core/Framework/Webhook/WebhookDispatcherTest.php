<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\WebhookCollection;
use Shopware\Core\Framework\Webhook\WebhookDispatcher;
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[CoversClass(WebhookDispatcher::class)]
class WebhookDispatcherTest extends TestCase
{
    private EventDispatcherInterface&MockObject $dispatcher;

    private Connection&MockObject $connection;

    private MockHandler $clientMock;

    private Client $client;

    private ContainerInterface $container;

    private HookableEventFactory&MockObject $eventFactory;

    private CollectingMessageBus $bus;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->clientMock = new MockHandler([new Response(200)]);
        $this->client = new Client(['handler' => HandlerStack::create($this->clientMock)]);
        $this->container = new Container();
        $this->eventFactory = $this->createMock(HookableEventFactory::class);
        $this->bus = new CollectingMessageBus();
    }

    public function testDispatchWithWebhooksSync(): void
    {
        $event = new AppFlowActionEvent('foobar', ['foo' => 'bar'], ['foo' => 'bar']);

        $webhookEntity = $this->getWebhookEntity($event->getName());
        $this->prepareContainer($webhookEntity);

        $this->dispatcher->expects(static::once())->method('dispatch')->with($event, $event->getName())->willReturn($event);
        $this->eventFactory->expects(static::once())->method('createHookablesFor')->with($event)->willReturn([$event]);

        $expectedRequest = new Request(
            'POST',
            $webhookEntity->getUrl(),
            [
                'foo' => 'bar',
                'Content-Type' => 'application/json',
                'sw-version' => '0.0.0',
                'sw-context-language' => [Defaults::LANGUAGE_SYSTEM],
                'sw-user-language' => [''],
            ],
            json_encode([
                'foo' => 'bar',
                'source' => [
                    'url' => 'https://example.com',
                    'appVersion' => $webhookEntity->getApp()?->getVersion(),
                    'shopId' => 'foobar',
                    'action' => $event->getName(),
                    'inAppPurchases' => [],
                ],
            ], \JSON_THROW_ON_ERROR)
        );

        $this->getWebhookDispatcher(true)->dispatch($event, $event->getName());

        $request = $this->clientMock->getLastRequest();

        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertEquals('foo.bar', $request->getUri()->getHost());

        $headers = $request->getHeaders();
        static::assertArrayHasKey(RequestSigner::SHOPWARE_SHOP_SIGNATURE, $headers);
        unset($headers[RequestSigner::SHOPWARE_SHOP_SIGNATURE], $headers['Content-Length'], $headers['User-Agent']);
        static::assertEquals($expectedRequest->getHeaders(), $headers);

        $expectedContents = json_decode($expectedRequest->getBody()->getContents(), true);
        $contents = json_decode($request->getBody()->getContents(), true);
        static::assertIsArray($contents);
        static::assertArrayHasKey('timestamp', $contents);
        static::assertArrayHasKey('source', $contents);
        static::assertArrayHasKey('eventId', $contents['source']);
        unset($contents['timestamp'], $contents['source']['eventId']);
        static::assertEquals($expectedContents, $contents);
    }

    public function testDispatchWithWebhooksAsync(): void
    {
        $event = new AppFlowActionEvent('foobar', ['foo' => 'bar'], ['foo' => 'bar']);

        $webhookEntity = $this->getWebhookEntity($event->getName());
        $this->prepareContainer($webhookEntity);
        $this->container->set('webhook_event_log.repository', new StaticEntityRepository([]));

        $this->dispatcher->expects(static::once())->method('dispatch')->with($event, $event->getName())->willReturn($event);
        $this->eventFactory->expects(static::once())->method('createHookablesFor')->with($event)->willReturn([$event]);

        $this->getWebhookDispatcher(false)->dispatch($event, $event->getName());

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);

        $payload = $message->getPayload();
        static::assertArrayHasKey('source', $payload);
        static::assertArrayHasKey('eventId', $payload['source']);
        unset($payload['source']['eventId']);
        static::assertEquals([
            'foo' => 'bar',
            'source' => [
                'url' => 'https://example.com',
                'appVersion' => $webhookEntity->getApp()?->getVersion(),
                'shopId' => 'foobar',
                'action' => $event->getName(),
                'inAppPurchases' => [],
            ],
        ], $payload);

        static::assertEquals($message->getLanguageId(), Defaults::LANGUAGE_SYSTEM);
        static::assertEquals($message->getAppId(), $webhookEntity->getApp()?->getId());
        static::assertEquals($message->getSecret(), $webhookEntity->getApp()?->getAppSecret());
        static::assertEquals($message->getShopwareVersion(), '0.0.0');
        static::assertEquals($message->getUrl(), 'https://foo.bar');
        static::assertEquals($message->getWebhookId(), $webhookEntity->getId());
    }

    public function testWebhookSettingForLiveVersionOnlyIsIgnoredIfEventTypeDoesNotMatch(): void
    {
        $event = new AppFlowActionEvent('foobar', ['foo' => 'bar'], ['foo' => 'bar']);

        $webhookEntity = $this->getWebhookEntity($event->getName());

        $webhookEntity->setOnlyLiveVersion(true);

        $this->prepareContainer($webhookEntity);
        $this->container->set('webhook_event_log.repository', new StaticEntityRepository([]));

        $this->dispatcher->expects(static::once())->method('dispatch')->with($event, $event->getName())->willReturn($event);
        $this->eventFactory->expects(static::once())->method('createHookablesFor')->with($event)->willReturn([$event]);

        $this->getWebhookDispatcher(false)->dispatch($event, $event->getName());

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksForLiveVersionOnlyAreCalledIfPayloadHasLiveVersion(): void
    {
        $entityRepository = new StaticEntityRepository([], new ProductDefinition());

        $event = $entityRepository->create([
            [
                'id' => Uuid::randomHex(),
                'versionId' => Defaults::LIVE_VERSION,
            ],
        ], Context::createDefaultContext());

        /** @var EntityWrittenEvent $eventByEntityName */
        $eventByEntityName = $event->getEventByEntityName('product');
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $webhookEntity = $this->getWebhookEntity('product.written');
        $webhookEntity->setOnlyLiveVersion(true);

        $this->prepareContainer($webhookEntity);
        $this->container->set('webhook_event_log.repository', new StaticEntityRepository([]));

        $this->dispatcher->expects(static::once())->method('dispatch')->with($event, 'product.written')->willReturn($event);
        $this->eventFactory->expects(static::once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        /** @var AppEntity $app */
        $app = $webhookEntity->getApp();

        $this->connection->expects(static::once())->method('fetchAllAssociative')->willReturn([
            [
                'id' => Uuid::fromHexToBytes($app->getAclRoleId()),
                'privileges' => json_encode(['product:read']),
            ],
        ]);

        $this->getWebhookDispatcher(false)->dispatch($event, 'product.written');

        $messages = $this->bus->getMessages();

        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksForLiveVersionOnlyAreIgnoredIfPayloadDoesNotHaveLiveVersion(): void
    {
        $entityRepository = new StaticEntityRepository([], new ProductDefinition());

        $event = $entityRepository->create([
            [
                'id' => Uuid::randomHex(),
                'versionId' => Uuid::randomHex(),
            ],
        ], Context::createDefaultContext());

        /** @var EntityWrittenEvent $eventByEntityName */
        $eventByEntityName = $event->getEventByEntityName('product');
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $webhookEntity = $this->getWebhookEntity('product.written');
        $webhookEntity->setOnlyLiveVersion(true);

        $this->container->set('webhook.repository', new StaticEntityRepository([new WebhookCollection([$webhookEntity])]));
        $this->container->set(AppLocaleProvider::class, $this->createMock(AppLocaleProvider::class));
        $this->container->set('webhook_event_log.repository', new StaticEntityRepository([]));

        $this->dispatcher->expects(static::once())->method('dispatch')->with($event, 'product.written')->willReturn($event);
        $this->eventFactory->expects(static::once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        $this->getWebhookDispatcher(false)->dispatch($event, 'product.written');

        $messages = $this->bus->getMessages();
        static::assertEmpty($messages);
    }

    public function testWebhooksAreCalledForNonLiveVersionConfig(): void
    {
        $entityRepository = new StaticEntityRepository([], new ProductDefinition());

        $event = $entityRepository->create([
            [
                'id' => Uuid::randomHex(),
                'versionId' => Uuid::randomHex(),
            ],
        ], Context::createDefaultContext());

        /** @var EntityWrittenEvent $eventByEntityName */
        $eventByEntityName = $event->getEventByEntityName('product');
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $webhookEntity = $this->getWebhookEntity('product.written');
        $webhookEntity->setOnlyLiveVersion(false);

        $this->prepareContainer($webhookEntity);
        $this->container->set('webhook_event_log.repository', new StaticEntityRepository([]));

        $this->dispatcher->expects(static::once())->method('dispatch')->with($event, 'product.written')->willReturn($event);
        $this->eventFactory->expects(static::once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        /** @var AppEntity $app */
        $app = $webhookEntity->getApp();

        $this->connection->expects(static::once())->method('fetchAllAssociative')->willReturn([
            [
                'id' => Uuid::fromHexToBytes($app->getAclRoleId()),
                'privileges' => json_encode(['product:read']),
            ],
        ]);

        $this->getWebhookDispatcher(false)->dispatch($event, 'product.written');

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testPayloadOfWebhookForLiveVersionOnlyIsFiltered(): void
    {
        $entityRepository = new StaticEntityRepository([], new ProductDefinition());

        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $event = $entityRepository->create([
            [
                'id' => $firstId,
                'versionId' => Defaults::LIVE_VERSION,
            ],
            [
                'id' => $secondId,
                'versionId' => Uuid::randomHex(),
            ],
        ], Context::createDefaultContext());

        /** @var EntityWrittenEvent $eventByEntityName */
        $eventByEntityName = $event->getEventByEntityName('product');
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $webhookEntity = $this->getWebhookEntity('product.written');
        $webhookEntity->setOnlyLiveVersion(true);

        $this->prepareContainer($webhookEntity);
        $this->container->set('webhook_event_log.repository', new StaticEntityRepository([]));

        $this->dispatcher->expects(static::once())->method('dispatch')->with($event, 'product.written')->willReturn($event);
        $this->eventFactory->expects(static::once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        /** @var AppEntity $app */
        $app = $webhookEntity->getApp();

        $this->connection->expects(static::once())->method('fetchAllAssociative')->willReturn([
            [
                'id' => Uuid::fromHexToBytes($app->getAclRoleId()),
                'privileges' => json_encode(['product:read']),
            ],
        ]);

        $this->getWebhookDispatcher(false)->dispatch($event, 'product.written');

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);

        $payload = $message->getPayload();
        static::assertCount(1, $payload['data']['payload']);
        static::assertNotFalse(json_encode($payload));
        static::assertStringContainsString($firstId, json_encode($payload));
        static::assertStringNotContainsString($secondId, json_encode($payload));
    }

    public function testPayloadIsLeftUnchangedForNonLiveVersionConfig(): void
    {
        $entityRepository = new StaticEntityRepository([], new ProductDefinition());

        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $event = $entityRepository->create([
            [
                'id' => $firstId,
                'versionId' => Defaults::LIVE_VERSION,
            ],
            [
                'id' => $secondId,
                'versionId' => Uuid::randomHex(),
            ],
        ], Context::createDefaultContext());

        /** @var EntityWrittenEvent $eventByEntityName */
        $eventByEntityName = $event->getEventByEntityName('product');
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $webhookEntity = $this->getWebhookEntity('product.written');
        $webhookEntity->setOnlyLiveVersion(false);

        $this->prepareContainer($webhookEntity);
        $this->container->set('webhook_event_log.repository', new StaticEntityRepository([]));

        $this->dispatcher->expects(static::once())->method('dispatch')->with($event, 'product.written')->willReturn($event);
        $this->eventFactory->expects(static::once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        /** @var AppEntity $app */
        $app = $webhookEntity->getApp();

        $this->connection->expects(static::once())->method('fetchAllAssociative')->willReturn([
            [
                'id' => Uuid::fromHexToBytes($app->getAclRoleId()),
                'privileges' => json_encode(['product:read']),
            ],
        ]);

        $this->getWebhookDispatcher(false)->dispatch($event, 'product.written');

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);

        $payload = $message->getPayload();
        static::assertCount(2, $payload['data']['payload']);
        static::assertNotFalse(json_encode($payload));
        static::assertStringContainsString($firstId, json_encode($payload));
        static::assertStringContainsString($secondId, json_encode($payload));
    }

    private function getWebhookDispatcher(bool $isAdminWorkerEnabled): WebhookDispatcher
    {
        return new WebhookDispatcher(
            $this->dispatcher,
            $this->connection,
            $this->client,
            'https://example.com',
            $this->container,
            $this->eventFactory,
            '0.0.0',
            $this->bus,
            $isAdminWorkerEnabled
        );
    }

    private function getWebhookEntity(string $eventName): WebhookEntity
    {
        $appEntity = new AppEntity();
        $appEntity->setId(Uuid::randomHex());
        $appEntity->setName('Cool App');
        $appEntity->setAclRoleId(Uuid::randomHex());
        $appEntity->setActive(true);
        $appEntity->setVersion('0.0.0');
        $appEntity->setAppSecret('verysecret');

        $webhookEntity = new WebhookEntity();
        $webhookEntity->setId(Uuid::randomHex());
        $webhookEntity->setName('Cool Webhook');
        $webhookEntity->setEventName($eventName);
        $webhookEntity->setApp($appEntity);
        $webhookEntity->setUrl('https://foo.bar');
        $webhookEntity->setOnlyLiveVersion(false);

        return $webhookEntity;
    }

    private function prepareContainer(WebhookEntity $webhookEntity): void
    {
        $appPayloadServiceHelper = $this->createMock(AppPayloadServiceHelper::class);
        $appPayloadServiceHelper->expects(static::once())->method('buildSource')->willReturn(new Source('https://example.com', 'foobar', '0.0.0'));

        $this->container->set('webhook.repository', new StaticEntityRepository([new WebhookCollection([$webhookEntity])]));
        $this->container->set(AppLocaleProvider::class, $this->createMock(AppLocaleProvider::class));
        $this->container->set(AppPayloadServiceHelper::class, $appPayloadServiceHelper);
    }
}
