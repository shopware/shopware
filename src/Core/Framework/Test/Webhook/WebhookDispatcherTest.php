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
use Shopware\Core\Framework\Webhook\WebhookDispatcher;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WebhookDispatcherTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $webhookRepository;

    /**
     * @var string
     */
    private $shopUrl;

    /**
     * @var ShopIdProvider
     */
    private $shopIdProvider;

    public function setUp(): void
    {
        $this->webhookRepository = $this->getContainer()->get('webhook.repository');
        $this->shopUrl = $_SERVER['APP_URL'];
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
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

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get(WebhookDispatcher::class);
        $event = $eventDispatcher->dispatch($event);
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
        $this->webhookRepository->upsert([
            [
                'name' => 'hook1',
                'eventName' => ProductEvents::PRODUCT_WRITTEN_EVENT,
                'url' => 'https://test.com',
            ],
        ], Context::createDefaultContext());

        $this->appendNewResponse(new Response(200));

        $id = Uuid::randomHex();

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->upsert([
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
        ], Context::createDefaultContext());

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
            'versionId',
            'id',
            'parentVersionId',
            'manufacturerId',
            'productManufacturerVersionId',
            'taxId',
            'stock',
            'price',
            'productNumber',
            'isCloseout',
            'purchaseSteps',
            'minPurchase',
            'shippingFree',
            'restockTime',
            'createdAt',
            'name',
            'active',
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
            $this->getContainer()->get(HookableEventFactory::class)
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
            $this->getContainer()->get(HookableEventFactory::class)
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
            $this->getContainer()->get(HookableEventFactory::class)
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
            $this->getContainer()->get(HookableEventFactory::class)
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

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDispatcher->dispatch($event);

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

        $clientMock = $this->createMock(Client::class);
        $clientMock->expects(static::never())
            ->method('sendAsync');

        $webhookDispatcher = new WebhookDispatcher(
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(Connection::class),
            $clientMock,
            $this->shopUrl,
            $this->getContainer(),
            $this->getContainer()->get(HookableEventFactory::class)
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
            $this->getContainer()->get(HookableEventFactory::class)
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

        /** @var PermissionPersister $permissionPersister */
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

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDispatcher->dispatch($event);

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

        /** @var PermissionPersister $permissionPersister */
        $permissionPersister = $this->getContainer()->get(PermissionPersister::class);
        $permissions = Permissions::fromArray([
            'customer' => ['read'],
        ]);

        $permissionPersister->updatePrivileges($permissions, $aclRoleId);

        /** @var SystemConfigService $systemConfigService */
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
            $this->getContainer()->get(HookableEventFactory::class)
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
            $this->getContainer()->get(HookableEventFactory::class)
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

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDispatcher->dispatch($event);

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
            $this->getContainer()->get(HookableEventFactory::class)
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

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDispatcher->dispatch($event);

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
