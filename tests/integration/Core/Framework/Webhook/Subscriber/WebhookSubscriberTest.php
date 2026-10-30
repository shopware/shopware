<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Shopware\Core\Test\Integration\Builder\Webhook\WebhookBuilder;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
class WebhookSubscriberTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private AppFixture $appFixture;

    private Connection $connection;

    protected function setUp(): void
    {
        $appFixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);
        $this->appFixture = $appFixture;
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testAWebhookOwnedByTheAppsIntegrationMovesToTheIntegrationThatReplacesIt(): void
    {
        $app = $this->appFixture->createAppFromData();
        $webhookId = WebhookBuilder::ownedByIntegration($app->getIntegrationId())
            ->insert($this->connection);

        $newIntegrationId = $this->replaceIntegration($app);
        static::getContainer()->get('integration.repository')->delete([['id' => $app->getIntegrationId()]], Context::createDefaultContext());

        static::assertSame($newIntegrationId, $this->loadWebhook($webhookId)->getOwnerIntegrationId());
    }

    public function testAWebhookMovesBackWhenTheAppSwitchesBackToItsPreviousIntegration(): void
    {
        $app = $this->appFixture->createAppFromData();
        $webhookId = WebhookBuilder::ownedByIntegration($app->getIntegrationId())
            ->insert($this->connection);
        $newIntegrationId = $this->replaceIntegration($app);
        static::assertSame($newIntegrationId, $this->loadWebhook($webhookId)->getOwnerIntegrationId());

        static::getContainer()->get('app.repository')->update([[
            'id' => $app->getId(),
            'integrationId' => $app->getIntegrationId(),
        ]], Context::createDefaultContext());
        static::getContainer()->get('integration.repository')->delete([['id' => $newIntegrationId]], Context::createDefaultContext());

        static::assertSame($app->getIntegrationId(), $this->loadWebhook($webhookId)->getOwnerIntegrationId());
    }

    public function testADeletedRoleIsRemovedFromTheWebhooksThatListIt(): void
    {
        $keptRoleId = $this->createRole();
        $deletedRoleId = $this->createRole();
        $webhookId = WebhookBuilder::ownedByUser(TestUser::createNewTestUser($this->connection)->getUserId())
            ->aclRoleIds([$keptRoleId, $deletedRoleId])
            ->insert($this->connection);

        static::getContainer()->get('acl_role.repository')->delete([['id' => $deletedRoleId]], Context::createDefaultContext());

        static::assertSame([$keptRoleId], $this->loadAclRoleIds($webhookId));
    }

    public function testAWebhookWhoseLastRoleIsDeletedKeepsAnEmptyList(): void
    {
        $roleId = $this->createRole();
        $webhookId = WebhookBuilder::ownedByUser(TestUser::createNewTestUser($this->connection)->getUserId())
            ->aclRoleIds([$roleId])
            ->insert($this->connection);

        static::getContainer()->get('acl_role.repository')->delete([['id' => $roleId]], Context::createDefaultContext());

        static::assertSame([], $this->loadAclRoleIds($webhookId));
    }

    private function replaceIntegration(AppEntity $app): string
    {
        static::getContainer()->get('app.repository')->update([[
            'id' => $app->getId(),
            'integration' => [
                'label' => $app->getName(),
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            ],
        ]], Context::createDefaultContext());

        return $this->appFixture->getApp($app->getId())->getIntegrationId();
    }

    private function loadWebhook(string $webhookId): WebhookEntity
    {
        $webhook = static::getContainer()->get('webhook.repository')
            ->search(new Criteria([$webhookId]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(WebhookEntity::class, $webhook);

        return $webhook;
    }

    private function createRole(): string
    {
        $roleId = Uuid::randomHex();

        static::getContainer()->get('acl_role.repository')->create([
            ['id' => $roleId, 'name' => $roleId, 'privileges' => ['product:read']],
        ], Context::createDefaultContext());

        return $roleId;
    }

    /**
     * @return list<string>
     */
    private function loadAclRoleIds(string $webhookId): array
    {
        $aclRoleIds = $this->connection->fetchOne(
            'SELECT acl_role_ids FROM webhook WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)],
        );
        static::assertIsString($aclRoleIds);

        return Json::decodeToList($aclRoleIds);
    }
}
