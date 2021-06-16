<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\WebhookDispatcher;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class WebhookDispatcherTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private EntityRepositoryInterface $webhookRepository;

    private string $shopUrl;

    private ShopIdProvider $shopIdProvider;

    private MessageBusInterface $bus;

    public function setUp(): void
    {
        $this->webhookRepository = $this->getContainer()->get('webhook.repository');
        $this->shopUrl = $_SERVER['APP_URL'];
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
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
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL),
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

        static::assertInstanceOf(CustomerBeforeLoginEvent::class, $event);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);
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
        ], json_decode($body, true));

        static::assertFalse($request->hasHeader('shopware-shop-signature'));
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

        $payload = json_decode($body, true);
        $actualUpdatedFields = $payload['data']['payload'][0]['updatedFields'];
        unset($payload['data']['payload'][0]['updatedFields']);

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
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL),
            'test@example.com'
        );

        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(static::never())
            ->method('sendAsync');

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $clientMock,
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

        $event = new BusinessEvent(
            MailSendSubscriber::ACTION_NAME,
            new CustomerBeforeLoginEvent(
                $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL),
                'test@example.com'
            )
        );

        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(static::never())
            ->method('sendAsync');

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $clientMock,
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
                'writeAccess' => false,
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
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL),
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
        ], json_decode($body, true));

        static::assertEquals(
            hash_hmac('sha256', $body, 's3cr3t'),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
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
                'writeAccess' => false,
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

        $event = new CustomerLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL),
            (new CustomerEntity())->assign(['firstName' => 'first', 'lastName' => 'last']),
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
                'writeAccess' => false,
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

        $event = new CustomerLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL),
            (new CustomerEntity())->assign(['firstName' => 'first', 'lastName' => 'last']),
            'testToken'
        );

        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(static::never())
            ->method('sendAsync');

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $clientMock,
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
                'writeAccess' => false,
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

        $event = new CustomerLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL),
            (new CustomerEntity())->assign(['firstName' => 'first', 'lastName' => 'last']),
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

        $data = json_decode($body, true);
        static::assertEquals('first', $data['data']['payload']['customer']['firstName']);
        static::assertEquals('last', $data['data']['payload']['customer']['lastName']);
        unset($data['data']['payload']['customer']);
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
                'writeAccess' => false,
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

        $event = new CustomerLoginEvent(
            $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL),
            (new CustomerEntity())->assign(['firstName' => 'first', 'lastName' => 'last']),
            'testToken'
        );

        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(static::never())
            ->method('sendAsync');

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $clientMock,
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
                'writeAccess' => false,
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

        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(static::never())
            ->method('sendAsync');

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $clientMock,
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
                'writeAccess' => false,
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

        $data = json_decode($body, true);

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
                'writeAccess' => false,
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

        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(static::never())
            ->method('sendAsync');

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $clientMock,
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
                'writeAccess' => false,
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

        $data = json_decode($body, true);

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
                'writeAccess' => false,
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

        $data = json_decode($body, true);

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
                'writeAccess' => false,
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

        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(static::never())
            ->method('sendAsync');

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

        $this->bus->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (WebhookEventMessage $message) use ($payload, $appId, $webhookId, $shopwareVersion) {
                static::assertEquals($payload, $message->getPayload());
                static::assertEquals($appId, $message->getAppId());
                static::assertEquals($webhookId, $message->getWebhookId());
                static::assertEquals($shopwareVersion, $message->getShopwareVersion());

                return true;
            }))
            ->willReturn(new Envelope(new WebhookEventMessage($webhookEventId, $payload, $appId, $webhookId, $shopwareVersion, 'https://test.com')));

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $clientMock,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
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
                'writeAccess' => false,
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

        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(static::never())
            ->method('sendAsync');

        $this->bus->expects(static::never())
            ->method('dispatch');

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $clientMock,
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

        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(static::never())
            ->method('sendAsync');

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
                static::assertEquals($payload, $message->getPayload());
                static::assertEquals($webhookId, $message->getWebhookId());
                static::assertEquals($shopwareVersion, $message->getShopwareVersion());

                return true;
            }))
            ->willReturn(new Envelope(new WebhookEventMessage($webhookEventId, $payload, null, $webhookId, $shopwareVersion, 'https://test.com')));

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $clientMock,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->bus,
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
}

class MockSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [];
    }
}
