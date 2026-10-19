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
