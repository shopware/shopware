<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Lifecycle\Persister\PermissionPersister;
use Shopware\Core\Framework\App\Manifest\Xml\Permissions;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopware\Core\Framework\Webhook\WebhookDispatcher;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Webhook\WebhookDispatcher
 */
class WebhookDispatcherTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    private string $shopUrl;

    private ShopIdProvider $shopIdProvider;

    private MessageBusInterface $bus;

    public function setUp(): void
    {
        $this->shopUrl = $_SERVER['APP_URL'];
        $this->shopIdProvider = $this->getContainer()->get(ShopIdProvider::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
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
                'password' => 'shopware',
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
