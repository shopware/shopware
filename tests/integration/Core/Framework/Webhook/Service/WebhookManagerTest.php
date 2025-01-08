<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Integration\App\GuzzleHistoryCollector;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(WebhookManager::class)]
class WebhookManagerTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use IntegrationTestBehaviour;

    private MessageBusInterface&MockObject $bus;

    private GuzzleHistoryCollector $guzzleHistory;

    private string $shopUrl;

    private ShopIdProvider $shopIdProvider;

    private Connection $connection;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    protected function setUp(): void
    {
        $this->shopUrl = $_SERVER['APP_URL'];
        $this->shopIdProvider = static::getContainer()->get(ShopIdProvider::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->connection = static::getContainer()->get(Connection::class);

        $guzzleHistory = static::getContainer()->get(GuzzleHistoryCollector::class);
        static::assertInstanceOf(GuzzleHistoryCollector::class, $guzzleHistory);
        $this->guzzleHistory = $guzzleHistory;

        $this->appRepository = static::getContainer()->get('app.repository');
    }

    public function testDoesNotDispatchBusinessEventIfAppIsInactive(): void
    {
        $appId = Uuid::randomHex();
        $aclRoleId = Uuid::randomHex();

        $this->createApp(
            appId: $appId,
            active: false,
            aclRoleId: $aclRoleId,
            permissions: ['customer' => ['read']]
        );

        $this->appendNewResponse(new Response(200));

        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);

        $customer = static::getContainer()->get('customer.repository')->search(new Criteria([$customerId]), Context::createDefaultContext())->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $event = new CustomerLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            $customer,
            'testToken'
        );

        $this->getManager()->dispatch($event);
    }

    public function testDoesNotDispatchBusinessEventIfAppHasNoPermission(): void
    {
        $this->createApp();

        $this->appendNewResponse(new Response(200));

        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);

        $customer = static::getContainer()->get('customer.repository')->search(new Criteria([$customerId]), Context::createDefaultContext())->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $event = new CustomerLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            $customer,
            'testToken'
        );

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $this->getManager($client)->dispatch($event);
    }

    public function testDispatchesBusinessEventIfAppHasPermission(): void
    {
        $appId = Uuid::randomHex();
        $aclRoleId = Uuid::randomHex();
        $this->createApp(appId: $appId, aclRoleId: $aclRoleId, permissions: ['customer' => ['read']]);

        $this->appendNewResponse(new Response(200));

        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);

        $customer = static::getContainer()->get('customer.repository')->search(new Criteria([$customerId]), Context::createDefaultContext())->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $event = new CustomerLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            $customer,
            'testToken'
        );

        $this->getManager()->dispatch($event);

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('Max', $data['data']['payload']['customer']['firstName']);
        static::assertSame('Mustermann', $data['data']['payload']['customer']['lastName']);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['data']['payload']['customer'], $data['source']['eventId']);
        static::assertSame([
            'data' => [
                'payload' => [
                    'contextToken' => 'testToken',
                ],
                'event' => CustomerLoginEvent::EVENT_NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'shopId' => $this->shopIdProvider->getShopId(),
                'appVersion' => '0.0.1',
                'inAppPurchases' => null,
            ],
        ], $data);

        static::assertSame(
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
        $this->createApp(appId: $appId, aclRoleId: $aclRoleId, permissions: ['customer' => ['read']]);

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => Uuid::randomHex(),
        ]);

        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);

        $customer = static::getContainer()->get('customer.repository')->search(new Criteria([$customerId]), Context::createDefaultContext())->get($customerId);
        static::assertInstanceOf(CustomerEntity::class, $customer);
        $event = new CustomerLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            $customer,
            'testToken'
        );

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $this->getManager($client)->dispatch($event);
    }

    public function testDispatchesBusinessEventToWebhookWithoutApp(): void
    {
        $this->createWebhook('hook1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://test.com');

        $this->appendNewResponse(new Response(200));

        $event = new CustomerBeforeLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );

        $this->getManager()->dispatch($event);

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $payload);
        static::assertArrayHasKey('eventId', $payload['source']);
        unset($payload['timestamp'], $payload['source']['eventId']);

        static::assertSame([
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
        $this->createWebhook('hook1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://test.com');
        $this->createWebhook('hook2', CustomerBeforeLoginEvent::EVENT_NAME, 'https://test2.com');

        $this->appendNewResponse(new Response(200));
        $this->appendNewResponse(new Response(200));

        $event = new CustomerBeforeLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );

        $this->getManager()->dispatch($event);

        $history = $this->guzzleHistory->getHistory();

        static::assertCount(2, $history);

        foreach ($history as $historyEntry) {
            $request = $historyEntry['request'];
            static::assertInstanceOf(Request::class, $request);

            $payload = json_decode($request->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertArrayHasKey('timestamp', $payload);
            static::assertArrayHasKey('eventId', $payload['source']);
            unset($payload['timestamp'], $payload['source']['eventId']);

            static::assertSame(
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
        $this->createWebhook('hook1', ProductEvents::PRODUCT_WRITTEN_EVENT, 'https://test.com');
        $context = Context::createDefaultContext();

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

        $this->getManager()->dispatch($event);

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $payload = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        $actualUpdatedFields = $payload['data']['payload'][0]['updatedFields'];
        static::assertArrayHasKey('timestamp', $payload);
        static::assertArrayHasKey('eventId', $payload['source']);
        unset($payload['data']['payload'][0]['updatedFields'], $payload['timestamp'], $payload['source']['eventId']);

        static::assertSame([
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
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $this->getManager($client)->dispatch($event);
    }

    public function testDoesntDispatchesWrappedBusinessEventToWebhook(): void
    {
        $this->createWebhook('hook1', CustomerBeforeLoginEvent::EVENT_NAME, 'https://test.com');

        $factory = static::getContainer()->get(FlowFactory::class);
        $event = $factory->create(new CustomerBeforeLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        ));
        $event->setFlowState(new FlowState());

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $this->getManager($client)->dispatch($event);
    }

    public function testDispatchesAllAppLifecycleSynchronously(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createApp(appId: $appId, aclRoleId: $aclRoleId, webhooks: [
            [
                'name' => 'hook1',
                'event_name' => AppDeletedEvent::NAME,
                'url' => 'https://test.com',
            ],
        ]);

        $this->appendNewResponse(new Response(200));

        $event = new AppDeletedEvent($appId, Context::createDefaultContext());

        $this->getManager(adminWorkerEnabled: false)->dispatch($event);

        $this->createMock(MessageBusInterface::class)->expects(static::never())
            ->method('dispatch');

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['source']['eventId']);

        static::assertSame([
            'data' => [
                'payload' => ['keepUserData' => false],
                'event' => AppDeletedEvent::NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'shopId' => $this->shopIdProvider->getShopId(),
                'appVersion' => '0.0.1',
                'inAppPurchases' => null,
            ],
        ], $data);

        static::assertSame(
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
        $this->createApp(appId: $appId, aclRoleId: $aclRoleId, active: false, webhooks: [
            [
                'name' => 'hook1',
                'event_name' => AppDeletedEvent::NAME,
                'url' => 'https://test.com',
            ],
        ]);

        $this->appendNewResponse(new Response(200));

        $event = new AppDeletedEvent($appId, Context::createDefaultContext());

        $this->getManager()->dispatch($event);

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['source']['eventId']);

        static::assertSame([
            'data' => [
                'payload' => ['keepUserData' => false],
                'event' => AppDeletedEvent::NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'shopId' => $this->shopIdProvider->getShopId(),
                'appVersion' => '0.0.1',
                'inAppPurchases' => null,
            ],
        ], $data);

        static::assertSame(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );
    }

    public function testItDoesNotDispatchWebhookMessageQueueWithAppInActive(): void
    {
        $aclRoleId = Uuid::randomHex();
        $this->createApp(
            active: false,
            aclRoleId: $aclRoleId,
            webhooks: [
                [
                    'name' => 'hook1',
                    'event_name' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                    'url' => 'https://test.com',
                ],
            ],
            permissions: ['product' => ['read']]
        );

        $entityId = Uuid::randomHex();
        $event = $this->getEntityWrittenEvent($entityId);

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $this->createMock(MessageBusInterface::class)->expects(static::never())
            ->method('dispatch');

        $this->getManager($client)->dispatch($event);
    }

    public function testItDoesNotDispatchGeneralEventsForDisabledApp(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $this->createApp(appId: $appId, aclRoleId: $aclRoleId, webhooks: [
            [
                'name' => 'hook1',
                'event_name' => 'product.written',
                'url' => 'https://test.com',
            ],
        ]);

        $event = $this->getEntityWrittenEvent(Uuid::randomHex());

        $webhookManager = $this->getManager();

        $before = $this->getLastRequest();

        $webhookManager->dispatch($event);

        static::assertSame($before, $this->getLastRequest());
    }

    public function testDoesNotDispatchAppLifecycleEventForUntouchedApp(): void
    {
        $aclRoleId = Uuid::randomHex();
        $this->createApp(aclRoleId: $aclRoleId, webhooks: [
            [
                'name' => 'hook1',
                'event_name' => AppDeletedEvent::NAME,
                'url' => 'https://test.com',
            ],
        ]);

        $this->appendNewResponse(new Response(200));

        // Deleted app is another app then the one subscriped to the deleted event
        $event = new AppDeletedEvent(Uuid::randomHex(), Context::createDefaultContext());

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $this->getManager($client)->dispatch($event);
    }

    public function testDoesNotDispatchEntityWrittenEventIfAppHasNotPermission(): void
    {
        $aclRoleId = Uuid::randomHex();
        $this->createApp(aclRoleId: $aclRoleId, webhooks: [
            [
                'name' => 'hook1',
                'event_name' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                'url' => 'https://test.com',
            ],
        ]);

        $this->appendNewResponse(new Response(200));

        $event = $this->getEntityWrittenEvent(Uuid::randomHex());

        $client = new Client([
            'handler' => new MockHandler([]),
        ]);

        $this->getManager($client)->dispatch($event);
    }

    public function testDispatchesAccessKeyIfWebhookHasApp(): void
    {
        $appId = Uuid::randomHex();
        $this->createApp(appId: $appId, webhooks: [
            [
                'name' => 'hook1',
                'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
                'url' => 'https://test.com',
            ],
        ]);

        $this->appendNewResponse(new Response(200));

        $event = new CustomerBeforeLoginEvent(
            static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL),
            'test@example.com'
        );

        $this->getManager()->dispatch($event);

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['source']['eventId']);

        static::assertSame([
            'data' => [
                'payload' => [
                    'email' => 'test@example.com',
                ],
                'event' => CustomerBeforeLoginEvent::EVENT_NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'shopId' => $this->shopIdProvider->getShopId(),
                'appVersion' => '0.0.1',
                'inAppPurchases' => null,
            ],
        ], $data);

        static::assertSame(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testDispatchesEntityWrittenEventIfAppHasPermission(): void
    {
        $appId = Uuid::randomHex();
        $aclRoleId = Uuid::randomHex();

        $this->createApp(appId: $appId, aclRoleId: $aclRoleId, webhooks: [
            [
                'name' => 'hook1',
                'event_name' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                'url' => 'https://test.com',
            ],
        ]);

        $permissionPersister = static::getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'permissions' => [
                'product' => ['read'],
            ],
        ]);

        $permissionPersister->updatePrivileges($permissions, $aclRoleId);

        $this->appendNewResponse(new Response(200));

        $entityId = Uuid::randomHex();
        $event = $this->getEntityWrittenEvent($entityId);

        $this->getManager()->dispatch($event);

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['source']['eventId']);

        static::assertSame([
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
                'shopId' => $this->shopIdProvider->getShopId(),
                'appVersion' => '0.0.1',
                'inAppPurchases' => null,
            ],
        ], $data);

        static::assertSame(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );
    }

    public function testDispatchesAppLifecycleEventForTouchedApp(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appId = Uuid::randomHex();

        $this->createApp(appId: $appId, aclRoleId: $aclRoleId, webhooks: [
            [
                'name' => 'hook1',
                'event_name' => AppDeletedEvent::NAME,
                'url' => 'https://test.com',
            ],
        ]);

        $this->appendNewResponse(new Response(200));

        $event = new AppDeletedEvent($appId, Context::createDefaultContext());

        $this->getManager()->dispatch($event);

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('timestamp', $data);
        static::assertArrayHasKey('eventId', $data['source']);
        unset($data['timestamp'], $data['source']['eventId']);

        static::assertSame([
            'data' => [
                'payload' => ['keepUserData' => false],
                'event' => AppDeletedEvent::NAME,
            ],
            'source' => [
                'url' => $this->shopUrl,
                'shopId' => $this->shopIdProvider->getShopId(),
                'appVersion' => '0.0.1',
                'inAppPurchases' => null,
            ],
        ], $data);

        static::assertSame(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testItDoesDispatchWebhookMessageQueueWithAppActive(): void
    {
        $aclRoleId = Uuid::randomHex();
        $appId = Uuid::randomHex();
        $webhookId = Uuid::randomHex();
        $this->createApp(
            appId: $appId,
            aclRoleId: $aclRoleId,
            webhooks: [
                [
                    'id' => $webhookId,
                    'name' => 'hook1',
                    'event_name' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                    'url' => 'https://test.com',
                ],
            ],
            permissions: ['product' => ['read']]
        );

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
                'shopId' => $this->shopIdProvider->getShopId(),
                'appVersion' => '0.0.1',
                'inAppPurchases' => null,
            ],
        ];

        $webhookEventId = Uuid::randomHex();

        $shopwareVersion = Kernel::SHOPWARE_FALLBACK_VERSION;

        $this->bus->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (WebhookEventMessage $message) use ($payload, $appId, $webhookId, $shopwareVersion) {
                $actualPayload = $message->getPayload();
                static::assertArrayHasKey('eventId', $actualPayload['source']);
                unset($actualPayload['source']['eventId']);
                static::assertSame($payload, $actualPayload);
                static::assertSame($appId, $message->getAppId());
                static::assertSame($webhookId, $message->getWebhookId());
                static::assertSame($shopwareVersion, $message->getShopwareVersion());
                static::assertSame('s3cr3t', $message->getSecret());
                static::assertSame(Defaults::LANGUAGE_SYSTEM, $message->getLanguageId());
                static::assertSame('en-GB', $message->getUserLocale());

                return true;
            }))
            ->willReturn(new Envelope(new WebhookEventMessage($webhookEventId, $payload, $appId, $webhookId, '6.4', 'http://test.com', 's3cr3t', Defaults::LANGUAGE_SYSTEM, 'en-GB')));

        $this->getManager($client, false)->dispatch($event);
    }

    public function testItDoesDispatchWebhookMessageQueueWithoutApp(): void
    {
        $webhookId = Uuid::randomHex();
        $this->createWebhook('hook1', ProductEvents::PRODUCT_WRITTEN_EVENT, 'https://test.com', null, $webhookId);

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
        $this->bus->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (WebhookEventMessage $message) use ($payload, $webhookId, $shopwareVersion) {
                $actualPayload = $message->getPayload();
                static::assertArrayHasKey('eventId', $actualPayload['source']);
                unset($actualPayload['source']['eventId']);
                static::assertSame($payload, $actualPayload);
                static::assertSame($webhookId, $message->getWebhookId());
                static::assertSame($shopwareVersion, $message->getShopwareVersion());
                static::assertNull($message->getAppId());
                static::assertNull($message->getSecret());
                static::assertSame(Defaults::LANGUAGE_SYSTEM, $message->getLanguageId());
                static::assertSame('en-GB', $message->getUserLocale());

                return true;
            }))
            ->willReturn(new Envelope(new WebhookEventMessage($webhookEventId, $payload, null, $webhookId, '6.4', 'http://test.com', 's3cr3t', Defaults::LANGUAGE_SYSTEM, 'en-GB')));

        $this->getManager($client, false)->dispatch($event);
    }

    private function createWebhook(string $name, string $eventName, string $url, ?string $appId = null, ?string $webhookId = null): void
    {
        $payload = array_filter([
            'id' => $webhookId ? Uuid::fromHexToBytes($webhookId) : Uuid::randomBytes(),
            'name' => $name,
            'event_name' => $eventName,
            'url' => $url,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'app_id' => $appId ? Uuid::fromHexToBytes($appId) : null,
        ]);

        $this->connection->insert('webhook', $payload);
    }

    /**
     * @param list<array{id?: string, name: string, event_name: string, url: string}>|null $webhooks
     * @param array<string, list<string>>|null $permissions
     */
    private function createApp(?string $appId = null, bool $active = true, ?string $aclRoleId = null, ?array $webhooks = null, ?array $permissions = null): void
    {
        $app = [
            'name' => 'SwagApp',
            'active' => $active,
            'path' => __DIR__ . '/../Manifest/_fixtures/test',
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
        ];

        if ($appId !== null) {
            $app['id'] = $appId;
        }

        if ($aclRoleId !== null) {
            $app['aclRole']['id'] = $aclRoleId;
        }

        $this->appRepository->create([$app], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $app['name']));
        $app = $this->appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($app);

        $webhooks = $webhooks ?? [
            [
                'name' => 'hook1',
                'event_name' => CustomerLoginEvent::EVENT_NAME,
                'url' => 'https://test.com',
            ],
        ];

        foreach ($webhooks as $webhook) {
            $this->createWebhook($webhook['name'], $webhook['event_name'], $webhook['url'], $app->getId(), $webhook['id'] ?? null);
        }

        if ($permissions !== null && $aclRoleId !== null) {
            $permissionPersister = static::getContainer()->get(PermissionPersister::class);
            $permissions = Permissions::fromArray([
                'permissions' => $permissions,
            ]);

            $permissionPersister->updatePrivileges($permissions, $aclRoleId);
        }
    }

    private function createCustomer(string $id): void
    {
        $addressId = Uuid::randomHex();
        $customer = [
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
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'test@gmail.com',
            'password' => 'shopware',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
            'vatIds' => ['DE123456789'],
            'company' => 'Test',
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        static::getContainer()->get('customer.repository')
            ->create([$customer], Context::createDefaultContext());
    }

    private function getManager(
        ?Client $client = null,
        bool $adminWorkerEnabled = true
    ): WebhookManager {
        return new WebhookManager(
            static::getContainer()->get(WebhookLoader::class),
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(HookableEventFactory::class),
            static::getContainer()->get(AppLocaleProvider::class),
            static::getContainer()->get(AppPayloadServiceHelper::class),
            $client ?? static::getContainer()->get('shopware.app_system.guzzle'),
            $this->bus,
            $this->shopUrl,
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $adminWorkerEnabled
        );
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
}
