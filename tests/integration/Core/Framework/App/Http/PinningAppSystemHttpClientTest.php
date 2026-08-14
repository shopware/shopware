<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Http\PinningAppSystemHttpClient;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;

/**
 * @internal
 */
#[Package('framework')]
class PinningAppSystemHttpClientTest extends TestCase
{
    public function testReplacesTheAppSystemClientService(): void
    {
        $client = static::getContainer()->get('shopware.app_system.guzzle');

        static::assertInstanceOf(PinningAppSystemHttpClient::class, $client);
        static::assertInstanceOf(ClientInterface::class, $client);
        static::assertNotInstanceOf(Client::class, $client);
    }

    public function testUsesAppSystemPolicyForPublicIpLiterals(): void
    {
        $appClient = static::getContainer()->get('shopware.app_system.guzzle');
        $webhookValidator = static::getContainer()->get(WebhookTargetValidator::class);

        $validator = (new \ReflectionProperty(PinningAppSystemHttpClient::class, 'targetValidator'))->getValue($appClient);

        static::assertInstanceOf(WebhookTargetValidator::class, $validator);
        static::assertNotNull($validator->validate('https://93.184.216.34/webhook'));
        static::assertNull($webhookValidator->validate('https://93.184.216.34/webhook'));
    }

    public function testIsInjectedIntoWebhookManager(): void
    {
        $webhookManager = static::getContainer()->get(WebhookManager::class);

        $client = (new \ReflectionProperty(WebhookManager::class, 'guzzle'))->getValue($webhookManager);

        static::assertInstanceOf(PinningAppSystemHttpClient::class, $client);
    }
}
