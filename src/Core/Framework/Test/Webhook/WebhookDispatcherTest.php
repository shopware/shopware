<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\WebhookDispatcher;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Integration\App\GuzzleHistoryCollector;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class WebhookDispatcherTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private EntityRepository $webhookRepository;

    private string $shopUrl;

    private ShopIdProvider $shopIdProvider;

    private MessageBusInterface $bus;

    private GuzzleHistoryCollector $guzzleHistory;

    protected function setUp(): void
    {
        $this->webhookRepository = $this->getContainer()->get('webhook.repository');
        $this->shopUrl = $_SERVER['APP_URL'];
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
        $this->bus = $this->createMock(MessageBusInterface::class);

        /** @var GuzzleHistoryCollector $guzzleHistory */
        $guzzleHistory = $this->getContainer()->get(GuzzleHistoryCollector::class);
        $this->guzzleHistory = $guzzleHistory;
    }

    public function testDispatchesBusinessEventToWebhookWithoutApp(): void
    {
        $this->webhookRepository->upsert([
            [
                'name' => 'hook1',
                'eventName' => CustomerBeforeLoginEvent::EVENT_NAME,
                'url' => 'https://test.com',
            ],
        ], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        $event = new CustomerBeforeLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        // check that event wasn't replaced
        $returnedEvent = $webhookDispatcher->dispatch($event);
        static::assertSame($event, $returnedEvent);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $payload);
        static::assertArrayHasKey('eventId', $payload['source']);
        unset($payload['timestamp'], $payload['source']['eventId']);

        static::assertEquals([
            'data' => [
                'payload' => [
                    'email' => 'test@example.com',
                ],
                'event' => CustomerBeforeLoginEvent::EVENT_NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
            ],
        ], $payload);

        static::assertFalse($request->hasHeader('shopware-shop-signature'));
    }

    public function testDispatchedWebhooksDontWrapEventMultipleTimes(): void
    {
        $this->webhookRepository->upsert([
            [
                'name' => 'hook1',
                'eventName' => CustomerBeforeLoginEvent::EVENT_NAME,
                'url' => 'https://test.com',
                'active' => true,
            ], [
                'name' => 'hook2',
                'eventName' => CustomerBeforeLoginEvent::EVENT_NAME,
                'url' => 'https://test.com',
                'active' => true,
            ],
        ], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));
        $this->appendNewResponse(new Response(200));

        $event = new CustomerBeforeLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);

        $history = $this->guzzleHistory->getHistory();

        static::assertCount(2, $history);

        foreach ($history as $historyEntry) {
            /** @var Request $request */
            $request = $historyEntry['request'];

            $payload = json_decode($request->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('timestamp', $payload);
            static::assertArrayHasKey('eventId', $payload['source']);
            unset($payload['timestamp'], $payload['source']['eventId']);

            static::assertEquals(
                [
                    'data' => [
                        'payload' => [
                            'email' => 'test@example.com',
                        ],
                        'event' => CustomerBeforeLoginEvent::EVENT_NAME,
                    ],
                    'source' => [
                        'url' => $this->shopUrl,
                    ],
                ],
                $payload
            );
        }
    }

    public function testDispatchesWrappedEntityWrittenEventToWebhookWithoutApp(): void
    {
        $context = Context::createDefaultContext();
        $this->webhookRepository->upsert([
            [
                'name' => 'hook1',
                'eventName' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                'url' => 'https://test.com',
            ],
        ], $context);

        $this->appendNewResponse(new Response(200));

        $id = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    ProductDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $id,
                            [
                                'id' => $id,
                                'name' => 'testProduct',
                                'productNumber' => 'SWC-1000',
                                'stock' => 100,
                                'manufacturer' => [
                                    'name' => 'app creator',
                                ],
                                'price' => [
                                    [
                                        'gross' => 100,
                                        'net' => 200,
                                        'linked' => false,
                                        'currencyId' => Defaults::CURRENCY,
                                    ],
                                ],
                                'tax' => [
                                    'name' => 'luxury',
                                    'taxRate' => '25',
                                ],
                            ],
                            ProductDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_INSERT,
                            null,
                            null
                        ),
                    ],
                    $context
                ),
            ]),
            []
        );

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );
        $webhookDispatcher->dispatch($event);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        $actualUpdatedFields = $payload['data']['payload'][0]['updatedFields'];
        static::assertArrayHasKey('timestamp', $payload);
        static::assertArrayHasKey('eventId', $payload['source']);
        unset($payload['data']['payload'][0]['updatedFields'], $payload['timestamp'], $payload['source']['eventId']);

        static::assertEquals([
            'data' => [
                'payload' => [[
                    'entity' => 'product',
                    'operation' => 'insert',
                    'primaryKey' => $id,
                ]],
                'event' => ProductEvents::PRODUCT_WRITTEN_EVENT,
            ],
            'source' => [
                'url' => $this->shopUrl,
            ],
        ], $payload);

        $expectedUpdatedFields = [
            'id',
            'manufacturer',
            'tax',
            'stock',
            'price',
            'productNumber',
            'name',
        ];

        foreach ($expectedUpdatedFields as $field) {
            static::assertContains($field, $actualUpdatedFields);
        }

        static::assertFalse($request->hasHeader('shopware-shop-signature'));
    }

    public function testNoRegisteredWebhook(): void
    {
        $event = new CustomerBeforeLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $client,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);
    }

    public function testDoesntDispatchesWrappedBusinessEventToWebhook(): void
    {
        $this->webhookRepository->upsert([
            [
                'name' => 'hook1',
                'eventName' => CustomerBeforeLoginEvent::EVENT_NAME,
                'url' => 'https://test.com',
            ],
        ], Context::createDefaultContext());

        $factory = $this->getContainer()->get(FlowFactory::class);
        $event = $factory->create(new CustomerBeforeLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        ));
        $event->setFlowState(new FlowState());

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $client,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);
    }

    public function testAddSubscriber(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects(static::once())
            ->method('addSubscriber');

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->addSubscriber(new MockSubscriber());
    }

    public function testRemoveSubscriber(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects(static::once())
            ->method('removeSubscriber');

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->removeSubscriber(new MockSubscriber());
    }

    public function testDispatchesAccessKeyIfWebhookHasApp(): void
    {
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
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => CustomerBeforeLoginEvent::EVENT_NAME,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        $event = new CustomerBeforeLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['source']['eventId']);

        static::assertEquals([
            'data' => [
                'payload' => [
                    'email' => 'test@example.com',
                ],
                'event' => CustomerBeforeLoginEvent::EVENT_NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'appVersion' => '0.0.1',
                'shopId' => $this->shopIdProvider->getShopId(),
            ],
        ], $data);

        static::assertEquals(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testDoesNotDispatchBusinessEventIfAppIsInactive(): void
    {
        $appId = Uuid::randomHex();
        $aclRoleId = Uuid::randomHex();
        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => false,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => CustomerLoginEvent::EVENT_NAME,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'customer' => ['read'],
        ]);

        $permissionPersister->updatePrivileges($permissions, $aclRoleId);

        $this->appendNewResponse(new Response(200));

        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);

        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), Context::createDefaultContext())->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $event = new CustomerLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            $customer,
            'testToken'
        );

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);
    }

    public function testDoesNotDispatchBusinessEventIfAppHasNoPermission(): void
    {
        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
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
                    'name' => 'hook1',
                    'eventName' => CustomerLoginEvent::EVENT_NAME,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);

        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), Context::createDefaultContext())->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $event = new CustomerLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            $customer,
            'testToken'
        );

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $client,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);
    }

    public function testDispatchesBusinessEventIfAppHasPermission(): void
    {
        $appId = Uuid::randomHex();
        $aclRoleId = Uuid::randomHex();
        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => CustomerLoginEvent::EVENT_NAME,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'customer' => ['read'],
        ]);

        $permissionPersister->updatePrivileges($permissions, $aclRoleId);

        $this->appendNewResponse(new Response(200));

        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);

        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), Context::createDefaultContext())->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $event = new CustomerLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            $customer,
            'testToken'
        );

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals('Max', $data['data']['payload']['customer']['firstName']);
        static::assertEquals('Mustermann', $data['data']['payload']['customer']['lastName']);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['data']['payload']['customer'], $data['source']['eventId']);
        static::assertEquals([
            'data' => [
                'payload' => [
                    'contextToken' => 'testToken',
                ],
                'event' => CustomerLoginEvent::EVENT_NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'appVersion' => '0.0.1',
                'shopId' => $this->shopIdProvider->getShopId(),
            ],
        ], $data);

        static::assertEquals(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testDoesNotDispatchBusinessEventIfAppUrlChangeWasDetected(): void
    {
        $appId = Uuid::randomHex();
        $aclRoleId = Uuid::randomHex();
        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => CustomerLoginEvent::EVENT_NAME,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'customer' => ['read'],
        ]);

        $permissionPersister->updatePrivileges($permissions, $aclRoleId);

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => Uuid::randomHex(),
        ]);

        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);

        $customer = $this->getContainer()->get('customer.repository')->search(new Criteria([$customerId]), Context::createDefaultContext())->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $event = new CustomerLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            $customer,
            'testToken'
        );

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $client,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);
    }

    public function testDoesNotDispatchEntityWrittenEventIfAppHasNotPermission(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        $event = $this->getEntityWrittenEvent(Uuid::randomHex());

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $client,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);
    }

    public function testDispatchesEntityWrittenEventIfAppHasPermission(): void
    {
        $appId = Uuid::randomHex();
        $aclRoleId = Uuid::randomHex();
        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'product' => ['read'],
        ]);

        $permissionPersister->updatePrivileges($permissions, $aclRoleId);

        $this->appendNewResponse(new Response(200));

        $entityId = Uuid::randomHex();
        $event = $this->getEntityWrittenEvent($entityId);

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['source']['eventId']);

        static::assertEquals([
            'data' => [
                'payload' => [
                    [
                        'entity' => 'product',
                        'operation' => 'delete',
                        'primaryKey' => $entityId,
                        'updatedFields' => ['id'],
                    ],
                ],
                'event' => ProductEvents::PRODUCT_WRITTEN_EVENT,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'appVersion' => '0.0.1',
                'shopId' => $this->shopIdProvider->getShopId(),
            ],
        ], $data);

        static::assertEquals(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );
    }

    public function testDoesNotDispatchAppLifecycleEventForUntouchedApp(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => AppDeletedEvent::NAME,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        // Deleted app is another app then the one subscriped to the deleted event
        $event = new AppDeletedEvent(Uuid::randomHex(), Context::createDefaultContext());

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $client,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);
    }

    public function testDispatchesAppLifecycleEventForTouchedApp(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appId = Uuid::randomHex();

        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => AppDeletedEvent::NAME,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        $event = new AppDeletedEvent($appId, Context::createDefaultContext());

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['source']['eventId']);

        static::assertEquals([
            'data' => [
                'payload' => [],
                'event' => AppDeletedEvent::NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'appVersion' => '0.0.1',
                'shopId' => $this->shopIdProvider->getShopId(),
            ],
        ], $data);

        static::assertEquals(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testDispatchesAllAppLifecycleSynchronously(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appId = Uuid::randomHex();

        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => AppDeletedEvent::NAME,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        $event = new AppDeletedEvent($appId, Context::createDefaultContext());

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            false
        );

        $this->createMock(MessageBusInterface::class)->expects(static::never())
            ->method('dispatch');

        $webhookDispatcher->dispatch($event);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['source']['eventId']);

        static::assertEquals([
            'data' => [
                'payload' => [],
                'event' => AppDeletedEvent::NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'appVersion' => '0.0.1',
                'shopId' => $this->shopIdProvider->getShopId(),
            ],
        ], $data);

        static::assertEquals(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testItDoesDispatchAppLifecycleEventForInactiveApp(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appId = Uuid::randomHex();

        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => false,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => AppDeletedEvent::NAME,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        $event = new AppDeletedEvent($appId, Context::createDefaultContext());

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('shopware.app_system.guzzle'),
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            true
        );

        $webhookDispatcher->dispatch($event);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['source']['eventId']);

        static::assertEquals([
            'data' => [
                'payload' => [],
                'event' => AppDeletedEvent::NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'appVersion' => '0.0.1',
                'shopId' => $this->shopIdProvider->getShopId(),
            ],
        ], $data);

        static::assertEquals(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );
    }

    public function testItDoesDispatchWebhookMessageQueueWithAppActive(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookId = Uuid::randomHex();
        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'id' => $appId,
            'name' => 'SwagApp',
            'active' => true,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'id' => $webhookId,
                    'name' => 'hook1',
                    'eventName' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'product' => ['read'],
        ]);

        $permissionPersister->updatePrivileges($permissions, $aclRoleId);

        $entityId = Uuid::randomHex();
        $event = $this->getEntityWrittenEvent($entityId);

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $payload = [
            'data' => [
                'payload' => [
                    [
                        'entity' => 'product',
                        'operation' => 'delete',
                        'primaryKey' => $entityId,
                        'updatedFields' => ['id'],
                    ],
                ],
                'event' => ProductEvents::PRODUCT_WRITTEN_EVENT,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'appVersion' => '0.0.1',
                'shopId' => $this->shopIdProvider->getShopId(),
            ],
        ];

        $webhookEventId = Uuid::randomHex();

        $shopwareVersion = Kernel::SHOPWARE_FALLBACK_VERSION;

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (WebhookEventMessage $message) use ($payload, $appId, $webhookId, $shopwareVersion) {
                $actualPayload = $message->getPayload();
                static::assertArrayHasKey('eventId', $actualPayload['source']);
                unset($actualPayload['source']['eventId']);
                static::assertEquals($payload, $actualPayload);
                static::assertEquals($appId, $message->getAppId());
                static::assertEquals($webhookId, $message->getWebhookId());
                static::assertEquals($shopwareVersion, $message->getShopwareVersion());
                static::assertEquals('s3cr3t', $message->getSecret());
                static::assertEquals(Defaults::LANGUAGE_SYSTEM, $message->getLanguageId());
                static::assertEquals('en-GB', $message->getUserLocale());

                return true;
            }))
            ->willReturn(new Envelope(new WebhookEventMessage($webhookEventId, $payload, $appId, $webhookId, '6.4', 'http://test.com', 's3cr3t', Defaults::LANGUAGE_SYSTEM, 'en-GB')));

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $client,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $bus,
            false
        );
        $webhookDispatcher->dispatch($event);
    }

    public function testItDoesNotDispatchWebhookMessageQueueWithAppInActive(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appRepository = $this->getContainer()->get('app.repository');
        $appRepository->create([[
            'name' => 'SwagApp',
            'active' => false,
            'path' => __DIR__ . '/Manifest/_fixtures/test',
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'id' => $aclRoleId,
                'name' => 'SwagApp',
            ],
            'webhooks' => [
                [
                    'name' => 'hook1',
                    'eventName' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                    'url' => 'https://test.com',
                ],
            ],
        ]], Context::createDefaultContext());

        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'product' => ['read'],
        ]);

        $permissionPersister->updatePrivileges($permissions, $aclRoleId);

        $entityId = Uuid::randomHex();
        $event = $this->getEntityWrittenEvent($entityId);

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $this->createMock(MessageBusInterface::class)->expects(static::never())
            ->method('dispatch');

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $client,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
            false
        );
        $webhookDispatcher->dispatch($event);
    }

    public function testItDoesDispatchWebhookMessageQueueWithoutApp(): void
    {
        $webhookId = Uuid::randomHex();
        $this->webhookRepository->upsert([
            [
                'id' => $webhookId,
                'name' => 'hook1',
                'eventName' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                'url' => 'https://test.com',
            ],
        ], Context::createDefaultContext());

        $entityId = Uuid::randomHex();
        $event = $this->getEntityWrittenEvent($entityId);

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $payload = [
            'data' => [
                'payload' => [
                    [
                        'entity' => 'product',
                        'operation' => 'delete',
                        'primaryKey' => $entityId,
                        'updatedFields' => ['id'],
                    ],
                ],
                'event' => ProductEvents::PRODUCT_WRITTEN_EVENT,
            ],
            'source' => [
                'url' => $this->shopUrl,
            ],
        ];

        $webhookEventId = Uuid::randomHex();
        $shopwareVersion = Kernel::SHOPWARE_FALLBACK_VERSION;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (WebhookEventMessage $message) use ($payload, $webhookId, $shopwareVersion) {
                $actualPayload = $message->getPayload();
                static::assertArrayHasKey('eventId', $actualPayload['source']);
                unset($actualPayload['source']['eventId']);
                static::assertEquals($payload, $actualPayload);
                static::assertEquals($webhookId, $message->getWebhookId());
                static::assertEquals($shopwareVersion, $message->getShopwareVersion());
                static::assertNull($message->getAppId());
                static::assertNull($message->getSecret());
                static::assertEquals(Defaults::LANGUAGE_SYSTEM, $message->getLanguageId());
                static::assertEquals('en-GB', $message->getUserLocale());

                return true;
            }))
            ->willReturn(new Envelope(new WebhookEventMessage($webhookEventId, $payload, null, $webhookId, '6.4', 'http://test.com', 's3cr3t', Defaults::LANGUAGE_SYSTEM, 'en-GB')));

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $client,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $bus,
            false
        );
        $webhookDispatcher->dispatch($event);
    }

    private function getEntityWrittenEvent(string $entityId): EntityWrittenContainerEvent
    {
        $context = Context::createDefaultContext();

        return new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    ProductDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $entityId,
                            [
                                'id' => $entityId,
                            ],
                            ProductDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_DELETE,
                            null,
                            null
                        ),
                    ],
                    $context
                ),
            ]),
            []
        );
    }

    private function createCustomer(string $id): void
    {
        $addressId = Uuid::randomHex();
        $this->getContainer()->get('customer.repository')->create([
            [
                'id' => $id,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'test@gmail.com',
                'password' => '123123123',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
                'vatIds' => ['DE123456789'],
                'company' => 'Test',
            ],
        ], Context::createDefaultContext());
    }
}

/**
 * @internal
 */
class MockSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [];
    }
}
