<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Ownership\OwnerType;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

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

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetWebhooksForEvent(): void
    {
        $ownerId = $this->createUserOwner(admin: true, roleId: null);

        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-1'),
            'name' => 'hook1',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'owner_user_id' => $ownerId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-2'),
            'name' => 'hook2',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test2.com',
            'owner_user_id' => $ownerId,
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

    #[DataProvider('ownerTypeProvider')]
    public function testGetWebhooksResolvesOwnerType(string $ownerKind, bool $admin, bool $withRole, OwnerType $expectedType): void
    {
        $roleId = $withRole ? $this->createAclRole(['product:read']) : null;

        $ownerId = match ($ownerKind) {
            'user' => $this->createUserOwner($admin, $roleId),
            'integration' => $this->createIntegrationOwner($admin, $roleId),
            default => throw new \LogicException('Unknown owner kind ' . $ownerKind),
        };

        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-1'),
            'name' => 'hook1',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'owner_user_id' => $ownerKind === 'user' ? $ownerId : null,
            'owner_integration_id' => $ownerKind === 'integration' ? $ownerId : null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $webhooks = static::getContainer()->get(WebhookLoader::class)->getWebhooks();

        static::assertCount(1, $webhooks);
        static::assertSame($expectedType, $webhooks[0]->ownerType);
        static::assertSame($roleId === null ? [] : [$roleId], $webhooks[0]->ownerRoleIds);
    }

    public function testGetWebhooksSkipsWebhooksWithoutAnOwner(): void
    {
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-1'),
            'name' => 'hook1',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        static::assertSame([], static::getContainer()->get(WebhookLoader::class)->getWebhooks());
    }

    /**
     * @return \Generator<string, array{0: string, 1: bool, 2: bool, 3: OwnerType}>
     */
    public static function ownerTypeProvider(): \Generator
    {
        yield 'admin user' => ['user', true, false, OwnerType::Admin];
        yield 'non-admin user with acl role' => ['user', false, true, OwnerType::Restricted];
        yield 'admin integration' => ['integration', true, false, OwnerType::Admin];
        yield 'non-admin integration with acl role' => ['integration', false, true, OwnerType::Restricted];
    }

    /**
     * @param list<string> $privileges
     */
    private function createAclRole(array $privileges): string
    {
        $roleId = Uuid::randomHex();

        $this->connection->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($roleId),
            'name' => 'webhook-owner-' . $roleId,
            'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $roleId;
    }

    private function createUserOwner(bool $admin, ?string $roleId): string
    {
        $user = $admin
            ? TestUser::createNewAdminTestUser($this->connection)
            : TestUser::createNewTestUser($this->connection);

        $userId = Uuid::fromHexToBytes($user->getUserId());

        if ($roleId !== null) {
            $this->connection->insert('acl_user_role', [
                'user_id' => $userId,
                'acl_role_id' => Uuid::fromHexToBytes($roleId),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        return $userId;
    }

    private function createIntegrationOwner(bool $admin, ?string $roleId): string
    {
        $integrationId = Uuid::randomBytes();

        $this->connection->insert('integration', [
            'id' => $integrationId,
            'access_key' => Uuid::randomHex(),
            'secret_access_key' => TestDefaults::HASHED_PASSWORD,
            'label' => 'webhook owner integration',
            'admin' => $admin ? 1 : 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($roleId !== null) {
            $this->connection->insert('integration_role', [
                'integration_id' => $integrationId,
                'acl_role_id' => Uuid::fromHexToBytes($roleId),
            ]);
        }

        return $integrationId;
    }
}
