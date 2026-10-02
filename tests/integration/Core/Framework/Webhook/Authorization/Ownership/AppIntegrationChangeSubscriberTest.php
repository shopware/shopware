<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook\Authorization\Ownership;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
class AppIntegrationChangeSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AppFixture $appFixture;

    protected function setUp(): void
    {
        $appFixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);
        $this->appFixture = $appFixture;
    }

    public function testAWebhookOwnedByTheAppsIntegrationMovesToTheIntegrationThatReplacesIt(): void
    {
        $app = $this->appFixture->createAppFromData();
        $webhookId = $this->createWebhookOwnedBy($app->getIntegrationId());

        $newIntegrationId = $this->replaceIntegration($app);
        static::getContainer()->get('integration.repository')->delete([['id' => $app->getIntegrationId()]], Context::createDefaultContext());

        static::assertSame($newIntegrationId, $this->loadWebhook($webhookId)->getOwnerIntegrationId());
    }

    public function testAWebhookMovesBackWhenTheAppSwitchesBackToItsPreviousIntegration(): void
    {
        $app = $this->appFixture->createAppFromData();
        $webhookId = $this->createWebhookOwnedBy($app->getIntegrationId());
        $newIntegrationId = $this->replaceIntegration($app);
        static::assertSame($newIntegrationId, $this->loadWebhook($webhookId)->getOwnerIntegrationId());

        static::getContainer()->get('app.repository')->update([[
            'id' => $app->getId(),
            'integrationId' => $app->getIntegrationId(),
        ]], Context::createDefaultContext());
        static::getContainer()->get('integration.repository')->delete([['id' => $newIntegrationId]], Context::createDefaultContext());

        static::assertSame($app->getIntegrationId(), $this->loadWebhook($webhookId)->getOwnerIntegrationId());
    }

    private function createWebhookOwnedBy(string $integrationId): string
    {
        $webhookId = Uuid::randomHex();

        static::getContainer()->get('webhook.repository')->create([[
            'id' => $webhookId,
            'name' => 'My super webhook',
            'eventName' => 'product.written',
            'url' => 'http://localhost',
            'ownerIntegrationId' => $integrationId,
        ]], Context::createDefaultContext());

        return $webhookId;
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
}
