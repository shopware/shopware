<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Framework\Webhook\Service\WebhookLoader;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
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
        $this->connection->insert('webhook', [
            'id' => $this->ids->getBytes('wh-1'),
            'name' => 'hook1',
            'event_name' => CustomerBeforeLoginEvent::EVENT_NAME,
            'url' => 'https://test.com',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('webhook', [
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
                    null,
                    null,
                    null,
                    false,
                    null,
                    null,
                    null,
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
                    null,
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
                    Uuid::fromBytesToHex($aclRoleId),
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
                    Uuid::fromBytesToHex($aclRoleId),
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
        static::assertInstanceOf(AclPrivilegeCollection::class, $permissions[$aclRoleId]);

        static::assertTrue($permissions[$aclRoleId]->isAllowed('customer', 'read'));
        static::assertTrue($permissions[$aclRoleId]->isAllowed('customer', 'create'));
        static::assertTrue($permissions[$aclRoleId]->isAllowed('category', 'read'));
    }
}
