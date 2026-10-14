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
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-1'),
            'name' => 'hook1',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'owner_user_id' => Uuid::fromHexToBytes($this->adminId),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-2'),
            'name' => 'hook2',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test2.com',
            'owner_user_id' => Uuid::fromHexToBytes($this->adminId),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

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

        $this->connection->insert('webhook', [
            'app_id' => $appId,
            'id' => $this->ids->getBytes('wh-1'),
            'name' => 'hook1',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('webhook', [
            'app_id' => $appId,
            'id' => $this->ids->getBytes('wh-2'),
            'name' => 'hook2',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test2.com',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

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
        $this->insertWebhook(ownerUserId: $user->getUserId());

        static::assertEquals(new AclRoles(false, [(string) $user->getAclRoleId()]), $this->loadWebhook()->roles);
    }

    public function testAnIntegrationOwnedWebhookHasTheIntegrationsRoles(): void
    {
        $integration = TestIntegration::create($this->connection, ['product:read']);
        $this->insertWebhook(ownerIntegrationId: $integration->getId());

        static::assertEquals(new AclRoles(false, [(string) $integration->getAclRoleId()]), $this->loadWebhook()->roles);
    }

    public function testGetWebhooksSkipsWebhooksWithoutAnOwner(): void
    {
        $this->insertWebhook();

        $loader = static::getContainer()->get(WebhookLoader::class);

        static::assertSame([], $loader->getWebhooks());
    }

    public function testAclRoleIdsLimitTheOwnersRoles(): void
    {
        $owner = TestUser::createNewTestUser($this->connection, ['product:read']);
        $orderRoleId = $this->createAclRole(['order:read']);
        $this->assignUserRole($owner->getUserId(), $orderRoleId);
        $this->insertWebhook(ownerUserId: $owner->getUserId(), aclRoleIds: [$orderRoleId, Uuid::randomHex()]);

        static::assertEquals(new AclRoles(false, [$orderRoleId]), $this->loadWebhook()->roles);
    }

    public function testAclRoleIdsRestrictAnAdminOwner(): void
    {
        $roleId = $this->createAclRole(['product:read']);
        $this->insertWebhook(ownerUserId: $this->adminId, aclRoleIds: [$roleId]);

        static::assertEquals(new AclRoles(false, [$roleId]), $this->loadWebhook()->roles);
    }

    public function testInactiveWebhooksAreNotLoaded(): void
    {
        $owner = TestUser::createNewTestUser($this->connection, ['product:read']);
        $this->insertWebhook(ownerUserId: $owner->getUserId(), active: false);

        static::assertSame([], static::getContainer()->get(WebhookLoader::class)->getWebhooks());
    }

    public function testGetSubscriptionsOfAUserOwnedWebhook(): void
    {
        $owner = TestUser::createNewTestUser($this->connection, ['product:read']);
        $roleId = $this->createAclRole(['product:read']);
        $this->insertWebhook(ownerUserId: $owner->getUserId(), aclRoleIds: [$roleId], active: false);

        static::assertEquals(
            [$this->ids->get('wh-1') => new WebhookSubscription(
                $this->ids->get('wh-1'),
                SubscriberType::User,
                $owner->getUserId(),
                CustomerBeforeLoginEvent::EVENT_NAME,
                [$roleId],
            )],
            static::getContainer()->get(WebhookLoader::class)->getSubscriptions([$this->ids->get('wh-1')])
        );
    }

    public function testGetSubscriptionsOfAnIntegrationOwnedWebhook(): void
    {
        $integrationId = TestIntegration::create($this->connection, ['product:read'])->getId();
        $this->insertWebhook(ownerIntegrationId: $integrationId);

        static::assertEquals(
            [$this->ids->get('wh-1') => new WebhookSubscription(
                $this->ids->get('wh-1'),
                SubscriberType::Integration,
                $integrationId,
                CustomerBeforeLoginEvent::EVENT_NAME,
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

        $this->insertWebhook(appId: $appId);

        static::assertEquals(
            [$this->ids->get('wh-1') => new WebhookSubscription(
                $this->ids->get('wh-1'),
                SubscriberType::App,
                $appId,
                CustomerBeforeLoginEvent::EVENT_NAME,
                null,
            )],
            static::getContainer()->get(WebhookLoader::class)->getSubscriptions([$this->ids->get('wh-1')])
        );

        $this->removeApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
    }

    public function testGetSubscriptionsSkipsAWebhookWithoutAnAppOrOwner(): void
    {
        $this->insertWebhook();

        static::assertSame([], static::getContainer()->get(WebhookLoader::class)->getSubscriptions([$this->ids->get('wh-1')]));
    }

    /**
     * @param list<string>|null $aclRoleIds
     */
    private function insertWebhook(?string $ownerUserId = null, ?string $ownerIntegrationId = null, ?array $aclRoleIds = null, bool $active = true, ?string $appId = null): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-1'),
            'app_id' => $appId ? Uuid::fromHexToBytes($appId) : null,
            'name' => 'hook1',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'active' => (int) $active,
            'owner_user_id' => $ownerUserId ? Uuid::fromHexToBytes($ownerUserId) : null,
            'owner_integration_id' => $ownerIntegrationId ? Uuid::fromHexToBytes($ownerIntegrationId) : null,
            'acl_role_ids' => $aclRoleIds === null ? null : json_encode($aclRoleIds, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
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
