<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclRoles;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestIntegration;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Shopware\Core\Framework\Webhook\Authorization\WebhookSubscription;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Test\Integration\Builder\Webhook\WebhookBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
class WebhookLoaderTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private Connection $connection;

    private string $adminId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);

        $adminId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM user WHERE username = :username', ['username' => 'admin']);
        static::assertIsString($adminId);
        $this->adminId = $adminId;
    }

    public function testGetWebhooksForEvent(): void
    {
        WebhookBuilder::ownedByUser($this->adminId)
            ->id($this->ids->get('wh-1'))
            ->subscribe(CustomerBeforeLoginEvent::EVENT_NAME, 'https://test.com')
            ->name('hook1')
            ->insert($this->connection);
        WebhookBuilder::ownedByUser($this->adminId)
            ->id($this->ids->get('wh-2'))
            ->subscribe(CustomerBeforeLoginEvent::EVENT_NAME, 'https://test2.com')
            ->name('hook2')
            ->insert($this->connection);

        $webhookLoader = static::getContainer()->get(WebhookLoader::class);

        $webhooks = $webhookLoader->getWebhooks();

        static::assertEquals(
            [
                new Webhook(
                    $this->ids->get('wh-1'),
                    'hook1',
                    'checkout.customer.before.login',
                    'https://test.com',
                    false,
                    null,
                    null,
                    null,
                    false,
                    null,
                    null,
                    roles: new AclRoles(true, []),
                ),
                new Webhook(
                    $this->ids->get('wh-2'),
                    'hook2',
                    'checkout.customer.before.login',
                    'https://test2.com',
                    false,
                    null,
                    null,
                    null,
                    false,
                    null,
                    null,
                    roles: new AclRoles(true, []),
                ),
            ],
            $webhooks
        );
    }

    public function testGetWebhooksForEventWithApp(): void
    {
        $this->installApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');

        $rows = $this->connection->fetchAllNumeric('SELECT id, acl_role_id FROM app WHERE name = \'minimal\'');

        static::assertCount(1, $rows);

        [$appId, $aclRoleId] = current($rows);

        WebhookBuilder::ofApp(Uuid::fromBytesToHex($appId))
            ->id($this->ids->get('wh-1'))
            ->subscribe(CustomerBeforeLoginEvent::EVENT_NAME, 'https://test.com')
            ->name('hook1')
            ->insert($this->connection);
        WebhookBuilder::ofApp(Uuid::fromBytesToHex($appId))
            ->id($this->ids->get('wh-2'))
            ->subscribe(CustomerBeforeLoginEvent::EVENT_NAME, 'https://test2.com')
            ->name('hook2')
            ->insert($this->connection);

        $webhookLoader = static::getContainer()->get(WebhookLoader::class);

        $webhooks = $webhookLoader->getWebhooks();

        static::assertEquals(
            [
                new Webhook(
                    $this->ids->get('wh-1'),
                    'hook1',
                    'checkout.customer.before.login',
                    'https://test.com',
                    false,
                    Uuid::fromBytesToHex($appId),
                    'minimal',
                    'local',
                    false,
                    '1.0.0',
                    'dont_tell',
                    roles: new AclRoles(false, [Uuid::fromBytesToHex($aclRoleId)]),
                ),
                new Webhook(
                    $this->ids->get('wh-2'),
                    'hook2',
                    'checkout.customer.before.login',
                    'https://test2.com',
                    false,
                    Uuid::fromBytesToHex($appId),
                    'minimal',
                    'local',
                    false,
                    '1.0.0',
                    'dont_tell',
                    roles: new AclRoles(false, [Uuid::fromBytesToHex($aclRoleId)]),
                ),
            ],
            $webhooks
        );

        $this->removeApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
    }

    public function testAUserOwnedWebhookHasTheUsersRoles(): void
    {
        $user = TestUser::createNewTestUser($this->connection, ['product:read']);
        WebhookBuilder::ownedByUser($user->getUserId())
            ->id($this->ids->get('wh-1'))
            ->insert($this->connection);

        static::assertEquals(new AclRoles(false, [(string) $user->getAclRoleId()]), $this->loadWebhook()->roles);
    }

    public function testAnIntegrationOwnedWebhookHasTheIntegrationsRoles(): void
    {
        $integration = TestIntegration::create($this->connection, ['product:read']);
        WebhookBuilder::ownedByIntegration($integration->getId())
            ->id($this->ids->get('wh-1'))
            ->insert($this->connection);

        static::assertEquals(new AclRoles(false, [(string) $integration->getAclRoleId()]), $this->loadWebhook()->roles);
    }

    public function testGetWebhooksSkipsWebhooksWithoutAnOwner(): void
    {
        WebhookBuilder::withoutOwner()
            ->id($this->ids->get('wh-1'))
            ->insert($this->connection);

        $loader = static::getContainer()->get(WebhookLoader::class);

        static::assertSame([], $loader->getWebhooks());
    }

    public function testAclRoleIdsLimitTheOwnersRoles(): void
    {
        $owner = TestUser::createNewTestUser($this->connection, ['product:read']);
        $orderRoleId = $this->createAclRole(['order:read']);
        $this->assignUserRole($owner->getUserId(), $orderRoleId);
        WebhookBuilder::ownedByUser($owner->getUserId())
            ->id($this->ids->get('wh-1'))
            ->aclRoleIds([$orderRoleId, Uuid::randomHex()])
            ->insert($this->connection);

        static::assertEquals(new AclRoles(false, [$orderRoleId]), $this->loadWebhook()->roles);
    }

    public function testAclRoleIdsRestrictAnAdminOwner(): void
    {
        $roleId = $this->createAclRole(['product:read']);
        WebhookBuilder::ownedByUser($this->adminId)
            ->id($this->ids->get('wh-1'))
            ->aclRoleIds([$roleId])
            ->insert($this->connection);

        static::assertEquals(new AclRoles(false, [$roleId]), $this->loadWebhook()->roles);
    }

    public function testInactiveWebhooksAreNotLoaded(): void
    {
        $owner = TestUser::createNewTestUser($this->connection, ['product:read']);
        WebhookBuilder::ownedByUser($owner->getUserId())
            ->id($this->ids->get('wh-1'))
            ->inactive()
            ->insert($this->connection);

        static::assertSame([], static::getContainer()->get(WebhookLoader::class)->getWebhooks());
    }

    public function testAWebhookOwnedByAnAppsIntegrationIsLoadedWithTheApp(): void
    {
        $this->installApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
        $app = $this->connection->fetchAssociative('SELECT LOWER(HEX(id)) AS id, LOWER(HEX(integration_id)) AS integrationId, LOWER(HEX(acl_role_id)) AS aclRoleId FROM app WHERE name = :name', ['name' => 'minimal']);
        static::assertIsArray($app);
        WebhookBuilder::ownedByIntegration($app['integrationId'])
            ->id($this->ids->get('wh-1'))
            ->subscribe(CustomerBeforeLoginEvent::EVENT_NAME, 'https://test.com')
            ->name('hook1')
            ->insert($this->connection);

        // also loaded while the app is inactive, the AppEventPolicy decides about the delivery
        static::assertEquals(
            new Webhook(
                $this->ids->get('wh-1'),
                'hook1',
                'checkout.customer.before.login',
                'https://test.com',
                false,
                $app['id'],
                'minimal',
                'local',
                false,
                '1.0.0',
                'dont_tell',
                roles: new AclRoles(false, [$app['aclRoleId']]),
            ),
            $this->loadWebhook()
        );

        $this->removeApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
    }

    public function testGetSubscriptionsOfAUserOwnedWebhook(): void
    {
        $owner = TestUser::createNewTestUser($this->connection, ['product:read']);
        $roleId = $this->createAclRole(['product:read']);
        WebhookBuilder::ownedByUser($owner->getUserId())
            ->id($this->ids->get('wh-1'))
            ->aclRoleIds([$roleId])
            ->inactive()
            ->insert($this->connection);

        static::assertEquals(
            [$this->ids->get('wh-1') => new WebhookSubscription(
                $this->ids->get('wh-1'),
                SubscriberType::User,
                $owner->getUserId(),
                'product.written',
                [$roleId],
            )],
            static::getContainer()->get(WebhookLoader::class)->getSubscriptions([$this->ids->get('wh-1')])
        );
    }

    public function testGetSubscriptionsOfAnIntegrationOwnedWebhook(): void
    {
        $integrationId = TestIntegration::create($this->connection, ['product:read'])->getId();
        WebhookBuilder::ownedByIntegration($integrationId)
            ->id($this->ids->get('wh-1'))
            ->insert($this->connection);

        static::assertEquals(
            [$this->ids->get('wh-1') => new WebhookSubscription(
                $this->ids->get('wh-1'),
                SubscriberType::Integration,
                $integrationId,
                'product.written',
                null,
            )],
            static::getContainer()->get(WebhookLoader::class)->getSubscriptions([$this->ids->get('wh-1')])
        );
    }

    public function testGetSubscriptionsOfAnAppWebhook(): void
    {
        $this->installApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
        $appId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM app WHERE name = :name', ['name' => 'minimal']);
        static::assertIsString($appId);

        WebhookBuilder::ofApp($appId)
            ->id($this->ids->get('wh-1'))
            ->insert($this->connection);

        static::assertEquals(
            [$this->ids->get('wh-1') => new WebhookSubscription(
                $this->ids->get('wh-1'),
                SubscriberType::App,
                $appId,
                'product.written',
                null,
            )],
            static::getContainer()->get(WebhookLoader::class)->getSubscriptions([$this->ids->get('wh-1')])
        );

        $this->removeApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
    }

    public function testGetSubscriptionsSkipsAWebhookWithoutAnAppOrOwner(): void
    {
        WebhookBuilder::withoutOwner()
            ->id($this->ids->get('wh-1'))
            ->insert($this->connection);

        static::assertSame([], static::getContainer()->get(WebhookLoader::class)->getSubscriptions([$this->ids->get('wh-1')]));
    }

    /**
     * @param list<string> $privileges
     */
    private function createAclRole(array $privileges): string
    {
        $roleId = Uuid::randomHex();

        $this->connection->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($roleId),
            'name' => $roleId,
            'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $roleId;
    }

    private function assignUserRole(string $userId, string $roleId): void
    {
        $this->connection->insert('acl_user_role', [
            'user_id' => Uuid::fromHexToBytes($userId),
            'acl_role_id' => Uuid::fromHexToBytes($roleId),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function loadWebhook(): Webhook
    {
        $webhooks = static::getContainer()->get(WebhookLoader::class)->getWebhooks();
        static::assertCount(1, $webhooks);

        return $webhooks[0];
    }
}
