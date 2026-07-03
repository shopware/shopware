<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Health\EndpointHealth;
use Shopware\Core\Framework\Webhook\Health\WebhookDispatchDecision;
use Shopware\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\DeliveryResponse;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEntry;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\Service\WebhookClient;
use Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\Service\WebhookRequest;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[CoversClass(WebhookManager::class)]
#[DisabledFeatures(['WEBHOOKS_REWORK'])]
class WebhookManagerTest extends TestCase
{
    private WebhookLoader&MockObject $webhookLoader;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private MockHandler $clientMock;

    private WebhookClient $webhookClient;

    private HookableEventFactory&MockObject $eventFactory;

    private CollectingMessageBus $bus;

    private WebhookOutboxStore&MockObject $webhookOutboxStore;

    private WebhookDeliveryService&MockObject $deliveryService;

    protected function setUp(): void
    {
        $this->webhookLoader = $this->createMock(WebhookLoader::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->clientMock = new MockHandler([new Response(200, [], '{}')]);
        $stack = HandlerStack::create($this->clientMock);
        $stack->push(new AuthMiddleware('6.7.0', $this->createMock(AppLocaleProvider::class)));
        $guzzle = new Client(['handler' => $stack]);
        $this->webhookClient = new WebhookClient($guzzle, new NativeClock());
        $this->eventFactory = $this->createMock(HookableEventFactory::class);
        $this->bus = new CollectingMessageBus();
        $this->webhookOutboxStore = $this->createMock(WebhookOutboxStore::class);
        $this->webhookOutboxStore->method('markRunning')->willReturn(new OutboxEntry(
            webhookEventId: 'stub',
            sequence: 1,
            executionCount: 1,
            deliveryStatus: 'running',
        ));
    }

    public function testDispatchesTwoConsecutiveEventsCorrectly(): void
    {
        $event1 = new AppFlowActionEvent('foobar', ['x-test-header' => 'test-header-val'], ['foo' => 'bar']);
        $event2 = new class('foobar.event', ['x-test-header' => 'test-header-val'], ['foo' => 'bar']) extends AppFlowActionEvent {};

        $this->eventFactory
            ->expects($this->exactly(2))
            ->method('createHookablesFor')
            ->willReturn([$event1], [$event2]);

        $webhookManager = $this->getWebhookManager(true);
        $webhookManager->dispatch($event1);
        $request = $this->clientMock->getLastRequest();
        static::assertNull($request);

        $webhook = $this->prepareWebhook($event2->getName());
        $this->assertSyncWebhookIsSent($webhook, $event2, $webhookManager);
    }

    public function testFlagOnSkipDecisionExcludesWebhookFromDelivery(): void
    {
        $event = $this->prepareEvent();

        $keep = $this->getWebhook('foobar');
        $drop = $this->getWebhook('foobar');

        $this->webhookLoader->method('getWebhooks')->willReturn([$keep, $drop]);
        $this->webhookLoader->method('getPrivilegesForRoles')->willReturnCallback(
            static fn (array $roleIds): array => array_fill_keys($roleIds, new AclPrivilegeCollection([])),
        );

        $endpointHealth = $this->createMock(EndpointHealth::class);
        $endpointHealth->method('gateFor')->willReturnCallback(
            static fn (string $webhookId): WebhookDispatchDecision => $webhookId === $keep->id
                ? WebhookDispatchDecision::Deliver
                : WebhookDispatchDecision::Skip,
        );

        $manager = $this->getWebhookManager(true, $endpointHealth);

        $this->deliveryService->expects($this->never())->method('hold');
        $this->deliveryService->expects($this->once())->method('process')->with(
            static::callback(static function (array $messages) use ($keep): bool {
                if (\count($messages) !== 1) {
                    return false;
                }

                static::assertInstanceOf(WebhookEventMessage::class, $messages[0]);

                return $messages[0]->getWebhookId() === $keep->id;
            }),
            false,
        );

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $manager->dispatch($event));
    }

    public function testFlagOnSkipDecisionForEveryWebhookDispatchesNothing(): void
    {
        $event = $this->prepareEvent();
        $this->prepareWebhook('foobar');

        $endpointHealth = $this->createMock(EndpointHealth::class);
        $endpointHealth->method('gateFor')->willReturn(WebhookDispatchDecision::Skip);

        $manager = $this->getWebhookManager(true, $endpointHealth);

        $this->deliveryService->expects($this->never())->method('process');
        $this->deliveryService->expects($this->never())->method('hold');

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $manager->dispatch($event));
    }

    public function testFlagOnHoldDecisionDispatchesViaHoldNotProcess(): void
    {
        $event = $this->prepareEvent();
        $webhook = $this->prepareWebhook('foobar');

        $endpointHealth = $this->createMock(EndpointHealth::class);
        $endpointHealth->method('gateFor')->willReturn(WebhookDispatchDecision::Hold);

        $manager = $this->getWebhookManager(true, $endpointHealth);

        // Held webhooks go through the delivery service's hold() path (→ transport persists paused),
        // never the deliver path.
        $this->deliveryService->expects($this->never())->method('process');
        $this->deliveryService->expects($this->once())->method('hold')->with(
            static::callback(static function (array $messages) use ($webhook): bool {
                if (\count($messages) !== 1) {
                    return false;
                }

                static::assertInstanceOf(WebhookEventMessage::class, $messages[0]);

                return $messages[0]->getWebhookId() === $webhook->id;
            }),
        );

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $manager->dispatch($event));
    }

    public function testFlagOnMixedDecisionRoutesEachWebhookToItsLane(): void
    {
        $event = $this->prepareEvent();
        $deliver = $this->getWebhook('foobar');
        $held = $this->getWebhook('foobar');
        $skip = $this->getWebhook('foobar');

        $this->webhookLoader->method('getWebhooks')->willReturn([$deliver, $held, $skip]);
        $this->webhookLoader->method('getPrivilegesForRoles')->willReturnCallback(
            static fn (array $roleIds): array => array_fill_keys($roleIds, new AclPrivilegeCollection([])),
        );

        $endpointHealth = $this->createMock(EndpointHealth::class);
        $endpointHealth->method('gateFor')->willReturnCallback(
            static fn (string $webhookId): WebhookDispatchDecision => match ($webhookId) {
                $deliver->id => WebhookDispatchDecision::Deliver,
                $held->id => WebhookDispatchDecision::Hold,
                default => WebhookDispatchDecision::Skip,
            },
        );

        $manager = $this->getWebhookManager(true, $endpointHealth);

        $this->deliveryService->expects($this->once())->method('process')->with(
            static::callback(static function (array $messages) use ($deliver): bool {
                if (\count($messages) !== 1) {
                    return false;
                }

                static::assertInstanceOf(WebhookEventMessage::class, $messages[0]);

                return $messages[0]->getWebhookId() === $deliver->id;
            }),
            false,
        );
        $this->deliveryService->expects($this->once())->method('hold')->with(
            static::callback(static function (array $messages) use ($held): bool {
                if (\count($messages) !== 1) {
                    return false;
                }

                static::assertInstanceOf(WebhookEventMessage::class, $messages[0]);

                return $messages[0]->getWebhookId() === $held->id;
            }),
        );

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $manager->dispatch($event));
    }

    public function testFlagOnDeliversEveryHealthyWebhookViaProcess(): void
    {
        $event = $this->prepareEvent();
        $first = $this->getWebhook('foobar');
        $second = $this->getWebhook('foobar');

        $this->webhookLoader->method('getWebhooks')->willReturn([$first, $second]);
        $this->webhookLoader->method('getPrivilegesForRoles')->willReturnCallback(
            static fn (array $roleIds): array => array_fill_keys($roleIds, new AclPrivilegeCollection([])),
        );

        // Every gate decides Deliver (HEALTHY): flag-on matches trunk — every webhook
        // delivered, nothing held.
        $manager = $this->getWebhookManager(true);

        $this->deliveryService->expects($this->never())->method('hold');
        $this->deliveryService->expects($this->once())->method('process')->with(
            static::callback(static fn (array $messages): bool => \count($messages) === 2),
            false,
        );

        Feature::withFeatureEnabled('WEBHOOKS_REWORK', fn () => $manager->dispatch($event));
    }

    public function testDispatchWithWebhooksSync(): void
    {
        $event = $this->prepareEvent();
        $webhook = $this->prepareWebhook($event->getName());

        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->with(
                static::isInstanceOf(OutboxEntry::class),
                static::anything(),
            );

        $this->assertSyncWebhookIsSent($webhook, $event);
    }

    public function testSyncDispatchMarksFailedOnNonUtf8FailureBody(): void
    {
        $event = $this->prepareEvent();
        $this->prepareWebhook($event->getName());

        $this->clientMock->reset();
        $this->clientMock->append(new Response(500, [], pack('C*', 0xB1)));

        $this->webhookOutboxStore->expects($this->once())->method('markFailed')
            ->with(
                static::isInstanceOf(OutboxEntry::class),
                static::callback(static fn (DeliveryResponse $r): bool => $r->responseContent === null && $r->responseStatusCode === 500),
            )
            ->willReturn(true);
        $this->webhookOutboxStore->expects($this->never())->method('markSuccess');

        $this->getWebhookManager(true)->dispatch($event);
    }

    public function testSyncDispatchMarksSuccessOnNonUtf8ResponseHeaders(): void
    {
        $event = $this->prepareEvent();
        $this->prepareWebhook($event->getName());

        $this->clientMock->reset();
        $this->clientMock->append(new Response(200, ['x-bad' => pack('C*', 0xB1)], '{}'));

        $this->webhookOutboxStore->expects($this->once())->method('markSuccess')
            ->with(
                static::isInstanceOf(OutboxEntry::class),
                static::callback(static fn (DeliveryResponse $r): bool => $r->responseContent === null && $r->responseStatusCode === 200),
            )
            ->willReturn(true);
        $this->webhookOutboxStore->expects($this->never())->method('markFailed');

        $this->getWebhookManager(true)->dispatch($event);
    }

    public function testDispatchWithWebhooksAsync(): void
    {
        $event = $this->prepareEvent();
        $webhook = $this->prepareWebhook($event->getName());

        $this->getWebhookManager(false)->dispatch($event);

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
                'appVersion' => $webhook->appVersion,
                'shopId' => 'foobar',
                'action' => $event->getName(),
                'inAppPurchases' => null,
            ],
        ], $payload);

        static::assertSame($message->getLanguageId(), Defaults::LANGUAGE_SYSTEM);
        static::assertSame($message->getAppId(), $webhook->appId);
        static::assertSame($message->getSecret(), $webhook->appSecret);
        static::assertSame($message->getShopwareVersion(), '0.0.0');
        static::assertSame($message->getUrl(), 'https://foo.bar');
        static::assertSame($message->getWebhookId(), $webhook->id);
        static::assertSame(['x-test-header' => 'test-header-val'], $message->getWebhookHeaders());
    }

    public function testWebhookSettingForLiveVersionOnlyIsIgnoredIfEventTypeDoesNotMatch(): void
    {
        $event = $this->prepareEvent();
        $this->prepareWebhook($event->getName(), true);

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksForLiveVersionOnlyAreCalledIfPayloadHasLiveVersion(): void
    {
        $event = $this->prepareHookableEvent();
        $this->prepareWebhook('product.written', true);

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();

        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksAreNotDispatchedIfPrivilegesAreMissing(): void
    {
        $event = $this->prepareHookableEvent();
        $this->prepareWebhook('product.written', true, []);

        $this->getWebhookManager(false)->dispatch($event);
        $messages = $this->bus->getMessages();
        static::assertEmpty($messages);
    }

    public function testWebhookCacheKeepsInactiveAppStateUntilCleared(): void
    {
        $event = new AppFlowActionEvent('commercial_license.provided', [], ['foo' => 'bar']);
        $inactiveWebhook = $this->getWebhook($event->getName(), appActive: false);
        $activeWebhook = $this->getWebhook($event->getName());

        $this->eventFactory
            ->expects($this->exactly(3))
            ->method('createHookablesFor')
            ->with($event)
            ->willReturn([$event]);

        $this->webhookLoader->expects($this->exactly(2))
            ->method('getWebhooks')
            ->willReturn([$inactiveWebhook], [$activeWebhook]);

        $this->webhookLoader
            ->method('getPrivilegesForRoles')
            ->willReturnCallback(static function (array $roleIds): array {
                $privileges = [];
                foreach ($roleIds as $roleId) {
                    $privileges[$roleId] = new AclPrivilegeCollection([]);
                }

                return $privileges;
            });

        $webhookManager = $this->getWebhookManager(false);

        $webhookManager->dispatch($event);
        static::assertCount(0, $this->bus->getMessages());

        $webhookManager->dispatch($event);
        static::assertCount(0, $this->bus->getMessages());

        $webhookManager->clearInternalWebhookCache();
        $webhookManager->dispatch($event);
        static::assertCount(1, $this->bus->getMessages());
    }

    public function testWebhooksForLiveVersionOnlyAreIgnoredIfPayloadHasDifferentVersion(): void
    {
        $event = $this->prepareHookableEvent([
            [
                'id' => Uuid::randomHex(),
                'versionId' => Uuid::randomHex(),
            ],
        ]);

        $this->prepareWebhook('product.written', true);

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertEmpty($messages);
    }

    public function testWebhooksForLiveVersionOnlyAreSentIfPayloadDoesNotHaveAnyVersionId(): void
    {
        $entityRepository = new StaticEntityRepository([], new CustomerDefinition());

        $event = $entityRepository->create([
            [
                'id' => Uuid::randomHex(),
            ],
        ], Context::createDefaultContext());

        $eventByEntityName = $event->getEventByEntityName('customer');
        static::assertInstanceOf(EntityWrittenEvent::class, $eventByEntityName);
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $this->eventFactory->expects($this->once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        $this->prepareWebhook('customer.written', true, ['customer:read']);

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testWebhooksAreCalledForNonLiveVersionConfig(): void
    {
        $event = $this->prepareHookableEvent();
        $this->prepareWebhook('product.written');

        $this->getWebhookManager(false)->dispatch($event);

        $messages = $this->bus->getMessages();
        static::assertCount(1, $messages);

        $envelop = $messages[0];
        static::assertInstanceOf(Envelope::class, $envelop);
        $message = $envelop->getMessage();
        static::assertInstanceOf(WebhookEventMessage::class, $message);
    }

    public function testPayloadOfWebhookForLiveVersionOnlyIsFiltered(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $payloads = [
            [
                'id' => $firstId,
                'versionId' => Defaults::LIVE_VERSION,
            ],
            [
                'id' => $secondId,
                'versionId' => Uuid::randomHex(),
            ],
        ];

        $event = $this->prepareHookableEvent($payloads);
        $this->prepareWebhook('product.written', true);

        $this->getWebhookManager(false)->dispatch($event);

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
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $payloads = [
            [
                'id' => $firstId,
                'versionId' => Defaults::LIVE_VERSION,
            ],
            [
                'id' => $secondId,
                'versionId' => Uuid::randomHex(),
            ],
        ];

        $event = $this->prepareHookableEvent($payloads);
        $this->prepareWebhook('product.written');

        $this->getWebhookManager(false)->dispatch($event);

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

    private function assertSyncWebhookIsSent(Webhook $webhook, AppFlowActionEvent $event, ?WebhookManager $webhookManager = null): void
    {
        $expectedRequest = new Request(
            'POST',
            $webhook->url,
            [
                'x-test-header' => 'test-header-val',
                'Content-Type' => 'application/json',
                'sw-version' => '0.0.0',
                'sw-context-language' => [Defaults::LANGUAGE_SYSTEM],
                'sw-user-language' => [''],
            ],
            json_encode([
                'foo' => 'bar',
                'source' => [
                    'url' => 'https://example.com',
                    'appVersion' => $webhook->appVersion,
                    'shopId' => 'foobar',
                    'action' => $event->getName(),
                    'inAppPurchases' => null,
                    'sequence' => 1,
                ],
            ], \JSON_THROW_ON_ERROR)
        );

        $webhookManager = $webhookManager ?? $this->getWebhookManager(true);
        $webhookManager->dispatch($event);

        $request = $this->clientMock->getLastRequest();

        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('foo.bar', $request->getUri()->getHost());

        $headers = $request->getHeaders();
        static::assertArrayHasKey(RequestSigner::SHOPWARE_SHOP_SIGNATURE, $headers);
        static::assertArrayHasKey('X-Shopware-Event-Id', $headers);
        static::assertArrayHasKey('X-Shopware-Sequence', $headers);
        static::assertArrayHasKey('X-Shopware-Attempt', $headers);
        static::assertSame(['1'], $headers['X-Shopware-Sequence']);
        static::assertSame(['0'], $headers['X-Shopware-Attempt']);
        unset(
            $headers[RequestSigner::SHOPWARE_SHOP_SIGNATURE],
            $headers['Content-Length'],
            $headers['User-Agent'],
            $headers['X-Shopware-Event-Id'],
            $headers['X-Shopware-Sequence'],
            $headers['X-Shopware-Attempt'],
        );
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

    private function prepareEvent(): AppFlowActionEvent
    {
        $event = new AppFlowActionEvent('foobar', ['x-test-header' => 'test-header-val'], ['foo' => 'bar']);

        $this->eventFactory
            ->expects($this->once())
            ->method('createHookablesFor')
            ->with($event)
            ->willReturn([$event]);

        return $event;
    }

    /**
     * @param list<array{id: string, versionId: string}>|null $payloads
     */
    private function prepareHookableEvent(?array $payloads = null): Event
    {
        $entityRepository = new StaticEntityRepository([], new ProductDefinition());

        $event = $entityRepository->create($payloads ?? [
            [
                'id' => Uuid::randomHex(),
                'versionId' => Defaults::LIVE_VERSION,
            ],
        ], Context::createDefaultContext());

        $eventByEntityName = $event->getEventByEntityName('product');
        static::assertInstanceOf(EntityWrittenEvent::class, $eventByEntityName);
        $hookableEvent = HookableEntityWrittenEvent::fromWrittenEvent($eventByEntityName);

        $this->eventFactory->expects($this->once())->method('createHookablesFor')->with($event)->willReturn([$hookableEvent]);

        return $event;
    }

    /**
     * @param list<string> $acl
     */
    private function prepareWebhook(string $eventName, bool $onlyLiveVersion = false, array $acl = ['product:read']): Webhook
    {
        $webhook = $this->getWebhook($eventName, $onlyLiveVersion);
        static::assertIsString($webhook->appAclRoleId);

        $this->webhookLoader->expects($this->once())
            ->method('getWebhooks')
            ->willReturn([$webhook]);

        $this->webhookLoader
            ->method('getPrivilegesForRoles')
            ->with([$webhook->appAclRoleId])
            ->willReturn([$webhook->appAclRoleId => new AclPrivilegeCollection($acl)]);

        return $webhook;
    }

    private function getWebhookManager(bool $isAdminWorkerEnabled, ?EndpointHealth $endpointHealth = null): WebhookManager
    {
        if ($endpointHealth === null) {
            // Default gate: every webhook reads HEALTHY (the source's fail-open for a missing
            // health row), so tests not exercising the gate deliver everything.
            $endpointHealth = $this->createMock(EndpointHealth::class);
            $endpointHealth->method('gateFor')->willReturn(WebhookDispatchDecision::Deliver);
        }

        $appPayloadServiceHelper = $this->createMock(AppPayloadServiceHelper::class);
        $appPayloadServiceHelper->method('buildSource')->willReturn(new Source('https://example.com', 'foobar', '0.0.0'));
        $appPayloadServiceHelper->method('createWebhookRequest')->willReturnCallback($this->buildWebhookRequest(...));

        $this->deliveryService = $this->createMock(WebhookDeliveryService::class);
        $this->deliveryService->method('buildRequest')->willReturnCallback(
            fn (WebhookEventMessage $message, OutboxEntry $entry): WebhookRequest => $this->buildWebhookRequestFromMessage($message, $entry, $appPayloadServiceHelper),
        );

        return new WebhookManager(
            $this->webhookLoader,
            $this->eventDispatcher,
            $this->eventFactory,
            $this->createMock(AppLocaleProvider::class),
            $appPayloadServiceHelper,
            $this->webhookClient,
            $this->bus,
            'https://example.com',
            '0.0.0',
            $isAdminWorkerEnabled,
            $this->deliveryService,
            $this->webhookOutboxStore,
            $endpointHealth,
        );
    }

    private function buildWebhookRequestFromMessage(
        WebhookEventMessage $message,
        OutboxEntry $entry,
        AppPayloadServiceHelper $helper,
    ): WebhookRequest {
        $payload = $message->getPayload();
        if (isset($payload['source']) && \is_array($payload['source'])) {
            $payload['source']['sequence'] = $entry->sequence;
        }

        $headers = $message->getWebhookHeaders();
        $headers[WebhookDeliveryService::HEADER_EVENT_ID] = $message->getWebhookEventId();
        $headers[WebhookDeliveryService::HEADER_SEQUENCE] = (string) $entry->sequence;
        $headers[WebhookDeliveryService::HEADER_ATTEMPT] = (string) max(0, $entry->executionCount - 1);

        return $helper->createWebhookRequest(
            $payload,
            $message->getUrl(),
            $message->getShopwareVersion(),
            WebhookClient::CONNECT_TIMEOUT,
            WebhookClient::REQUEST_TIMEOUT,
            $message->getSecret(),
            $message->getLanguageId(),
            $message->getUserLocale(),
            $headers,
        );
    }

    /**
     * Minimal stand-in for AppPayloadServiceHelper::createWebhookRequest.
     * The real method is unit-tested in AppPayloadServiceHelperTest; here we only
     * need a valid WebhookRequest so the Guzzle MockHandler receives a sendable request.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $webhookHeaders
     */
    private function buildWebhookRequest(
        array $payload,
        string $url,
        string $shopwareVersion,
        int $connectionTimeout,
        int $requestTimeout,
        ?string $secret = null,
        ?string $languageId = null,
        ?string $userLocale = null,
        array $webhookHeaders = [],
    ): WebhookRequest {
        $payload['timestamp'] = time();
        $jsonPayload = json_encode($payload, \JSON_THROW_ON_ERROR);

        $headers = ['Content-Type' => 'application/json', 'sw-version' => $shopwareVersion, ...$webhookHeaders];
        if ($languageId !== null && $userLocale !== null) {
            $headers[AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE] = $languageId;
            $headers[AuthMiddleware::SHOPWARE_USER_LANGUAGE] = $userLocale;
        }

        $options = ['connect_timeout' => $connectionTimeout, 'timeout' => $requestTimeout];
        if ($secret !== null) {
            $options[AuthMiddleware::APP_REQUEST_TYPE] = [AuthMiddleware::APP_SECRET => $secret];
        }

        return new WebhookRequest(new Request('POST', $url, $headers, $jsonPayload), $headers, $jsonPayload, time(), $options);
    }

    private function getWebhook(string $eventName, bool $onlyLiveVersion = false, bool $appActive = true): Webhook
    {
        return new Webhook(
            Uuid::randomHex(),
            'Cool Webhook',
            $eventName,
            'https://foo.bar',
            $onlyLiveVersion,
            Uuid::randomHex(),
            'Cool App',
            'local',
            $appActive,
            '0.0.0',
            'verysecret',
            Uuid::randomHex()
        );
    }
}
