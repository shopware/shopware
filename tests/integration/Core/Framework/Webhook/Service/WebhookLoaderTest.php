<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestIntegration;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\OwnerType;
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
                    ownerType: OwnerType::Admin,
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
                    ownerType: OwnerType::Admin,
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
                    ownerType: OwnerType::Restricted,
                    ownerRoleIds: [Uuid::fromBytesToHex($aclRoleId)],
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
                    ownerType: OwnerType::Restricted,
                    ownerRoleIds: [Uuid::fromBytesToHex($aclRoleId)],
                ),
            ],
            $webhooks
        );

        $this->removeApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
    }

    public function testGetPrivilegesForRoles(): void
    {
        $aclRoleId = Uuid::randomHex();

        $this->connection->insert(
            'acl_role',
            [
                'id' => Uuid::fromHexToBytes($aclRoleId),
                'name' => 'SomeApp',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'privileges' => json_encode(['customer:read', 'customer:create', 'category:read'], \JSON_THROW_ON_ERROR),
            ]
        );

        $webhookLoader = static::getContainer()->get(WebhookLoader::class);

        $permissions = $webhookLoader->getPrivilegesForRoles([$aclRoleId]);

        static::assertCount(1, $permissions);
        static::assertArrayHasKey($aclRoleId, $permissions);

        static::assertTrue($permissions[$aclRoleId]->isAllowed('customer', 'read'));
        static::assertTrue($permissions[$aclRoleId]->isAllowed('customer', 'create'));
        static::assertTrue($permissions[$aclRoleId]->isAllowed('category', 'read'));
    }

    public function testAnAdminUserIsAnAdminOwner(): void
    {
        $this->insertWebhook(ownerUserId: $this->adminId);

        static::assertSame(OwnerType::Admin, $this->loadWebhook()->ownerType);
    }

    public function testANonAdminUserIsARestrictedOwnerWithItsRole(): void
    {
        $user = TestUser::createNewTestUser($this->connection, ['product:read']);
        $this->insertWebhook(ownerUserId: $user->getUserId());

        $webhook = $this->loadWebhook();
        static::assertSame(OwnerType::Restricted, $webhook->ownerType);
        static::assertSame([$user->getAclRoleId()], $webhook->ownerRoleIds);
    }

    public function testAnAdminIntegrationIsAnAdminOwner(): void
    {
        $this->insertWebhook(ownerIntegrationId: TestIntegration::createAdmin($this->connection)->getId());

        static::assertSame(OwnerType::Admin, $this->loadWebhook()->ownerType);
    }

    public function testANonAdminIntegrationIsARestrictedOwnerWithItsRole(): void
    {
        $integration = TestIntegration::create($this->connection, ['product:read']);
        $this->insertWebhook(ownerIntegrationId: $integration->getId());

        $webhook = $this->loadWebhook();
        static::assertSame(OwnerType::Restricted, $webhook->ownerType);
        static::assertSame([$integration->getAclRoleId()], $webhook->ownerRoleIds);
    }

    public function testGetWebhooksSkipsWebhooksWithoutAnOwner(): void
    {
        $this->insertWebhook();

        $loader = static::getContainer()->get(WebhookLoader::class);

        static::assertSame([], $loader->getWebhooks());
        static::assertSame([], $loader->getWebhooksByIds([$this->ids->get('wh-1')]));
    }

    public function testAclRoleIdsLimitTheOwnersRoles(): void
    {
        $owner = TestUser::createNewTestUser($this->connection, ['product:read']);
        $orderRoleId = $this->createAclRole(['order:read']);
        $this->assignUserRole($owner->getUserId(), $orderRoleId);
        $this->insertWebhook(ownerUserId: $owner->getUserId(), aclRoleIds: [$orderRoleId, Uuid::randomHex()]);

        $webhook = $this->loadWebhook();
        static::assertSame(OwnerType::Restricted, $webhook->ownerType);
        static::assertSame([$orderRoleId], $webhook->ownerRoleIds);
    }

    public function testAclRoleIdsRestrictAnAdminOwner(): void
    {
        $roleId = $this->createAclRole(['product:read']);
        $this->insertWebhook(ownerUserId: $this->adminId, aclRoleIds: [$roleId]);

        $webhook = $this->loadWebhook();
        static::assertSame(OwnerType::Restricted, $webhook->ownerType);
        static::assertSame([$roleId], $webhook->ownerRoleIds);
    }

    public function testEmptyAclRoleIdsAreIgnored(): void
    {
        $roleId = $this->createAclRole(['product:read']);
        $this->insertWebhook(ownerUserId: $this->adminId, aclRoleIds: [$roleId, null, '']);

        $webhook = $this->loadWebhook();
        static::assertSame([$roleId], $webhook->aclRoleIds);
        static::assertSame([$roleId], $webhook->ownerRoleIds);
    }

    public function testInactiveWebhooksAreLoadedByIds(): void
    {
        $owner = TestUser::createNewTestUser($this->connection, ['product:read']);
        $this->insertWebhook(ownerUserId: $owner->getUserId(), active: false);

        $loader = static::getContainer()->get(WebhookLoader::class);
        $webhooks = $loader->getWebhooksByIds([$this->ids->get('wh-1')]);

        static::assertSame([], $loader->getWebhooks());
        static::assertCount(1, $webhooks);
        static::assertSame($this->ids->get('wh-1'), $webhooks[0]->id);
        static::assertSame(OwnerType::Restricted, $webhooks[0]->ownerType);
        static::assertSame([$owner->getAclRoleId()], $webhooks[0]->ownerRoleIds);
    }

    /**
     * @param list<string|null>|null $aclRoleIds
     */
    private function insertWebhook(?string $ownerUserId = null, ?string $ownerIntegrationId = null, ?array $aclRoleIds = null, bool $active = true): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-1'),
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
