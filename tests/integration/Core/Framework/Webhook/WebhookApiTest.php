<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookEntity;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[Package('framework')]
class WebhookApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testWriteWebhookViaApi(): void
    {
        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/webhook/',
            [
                'name' => 'My super webhook',
                'eventName' => 'product.written',
                'url' => 'http://127.0.0.1',
            ]
        );

        $response = $this->getBrowser()->getResponse();

        static::assertSame(204, $response->getStatusCode(), \print_r($response->getContent(), true));
    }

    public function testAWebhookCannotBeCreatedForAnApp(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/webhook/', [
            'name' => 'My super webhook',
            'eventName' => 'product.written',
            'url' => 'http://localhost',
            'appId' => Uuid::randomHex(),
        ]);

        $this->assertApiErrors([['code' => 'FRAMEWORK__WRITE_CONSTRAINT_VIOLATION', 'field' => 'appId']]);
    }

    public function testAWebhookCannotBeCreatedForSomeoneElse(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/webhook/', [
            'name' => 'My super webhook',
            'eventName' => 'product.written',
            'url' => 'http://localhost',
            'ownerUserId' => Uuid::randomHex(),
        ]);

        $this->assertApiErrors([['code' => 'FRAMEWORK__WRITE_CONSTRAINT_VIOLATION', 'field' => 'ownerUserId']]);
    }

    public function testTheUserCreatingAWebhookOwnsIt(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $user = TestUser::createNewTestUser($connection, ['webhook:create']);
        $user->authorizeBrowser($this->getBrowser());

        $webhookId = Uuid::randomHex();
        $this->getBrowser()->jsonRequest('POST', '/api/webhook/', [
            'id' => $webhookId,
            'name' => 'My super webhook',
            'eventName' => 'product.written',
            'url' => 'http://localhost',
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $webhook = $this->loadWebhook($webhookId);
        static::assertSame($user->getUserId(), $webhook->getOwnerUserId());
        static::assertNull($webhook->getOwnerIntegrationId());
    }

    public function testTheIntegrationCreatingAWebhookOwnsIt(): void
    {
        $integrationId = Uuid::randomHex();
        $this->authorizeBrowserWithIntegration($this->getBrowser(), $integrationId);

        $webhookId = Uuid::randomHex();
        $this->getBrowser()->jsonRequest('POST', '/api/webhook/', [
            'id' => $webhookId,
            'name' => 'My super webhook',
            'eventName' => 'product.written',
            'url' => 'http://localhost',
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $webhook = $this->loadWebhook($webhookId);
        static::assertSame($integrationId, $webhook->getOwnerIntegrationId());
        static::assertNull($webhook->getOwnerUserId());
    }

    public function testAppWebhookCannotBeUpdatedViaApi(): void
    {
        $webhookId = $this->createAppWebhook();

        $this->getBrowser()->jsonRequest('PATCH', '/api/webhook/' . $webhookId, [
            'url' => 'https://attacker.example',
        ]);

        $this->assertApiErrors([['code' => WebhookException::APP_WEBHOOK_NOT_MODIFIABLE]]);
        static::assertSame('https://app.example', $this->loadWebhook($webhookId)->getUrl());
    }

    public function testAppWebhookCannotBeDeletedViaApi(): void
    {
        $webhookId = $this->createAppWebhook();

        $this->getBrowser()->jsonRequest('DELETE', '/api/webhook/' . $webhookId);

        $this->assertApiErrors([['code' => WebhookException::APP_WEBHOOK_NOT_MODIFIABLE]]);
        static::assertSame('https://app.example', $this->loadWebhook($webhookId)->getUrl());
    }

    public function testDeletingAnAppStillDeletesItsWebhooks(): void
    {
        $webhookId = $this->createAppWebhook();

        $connection = static::getContainer()->get(Connection::class);
        $appId = $connection->fetchOne(
            'SELECT LOWER(HEX(app_id)) FROM `webhook` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($webhookId)]
        );

        $this->getBrowser()->jsonRequest('DELETE', '/api/app/' . $appId);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        static::assertFalse(
            $connection->fetchOne(
                'SELECT 1 FROM `webhook` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes($webhookId)]
            )
        );
    }

    private function createAppWebhook(): string
    {
        $webhookId = Uuid::randomHex();

        (new AppFixture(static::getContainer()->get('app.repository')))->createAppFromData([
            'webhooks' => [[
                'id' => $webhookId,
                'name' => 'App webhook',
                'eventName' => 'product.written',
                'url' => 'https://app.example',
            ]],
        ]);

        return $webhookId;
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
