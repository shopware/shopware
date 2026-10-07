<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Http;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;

/**
 * @internal
 */
#[Package('framework')]
class AppSystemHttpMiddlewareTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testKeepsTheNativeAppSystemClientService(): void
    {
        $client = static::getContainer()->get('shopware.app_system.guzzle');

        static::assertInstanceOf(Client::class, $client);
    }

    public function testUsesAppSystemPolicyForPublicIpLiterals(): void
    {
        $resolver = static::getContainer()->get('shopware.app_system.trusted_url_resolver');
        $webhookValidator = static::getContainer()->get(WebhookTargetValidator::class);

        static::assertInstanceOf(TrustedUrlResolver::class, $resolver);
        static::assertSame('93.184.216.34', $resolver->resolve('https://93.184.216.34/webhook')->ip);
        static::assertNull($webhookValidator->validate('https://93.184.216.34/webhook'));
    }

    public function testIsInjectedIntoWebhookManager(): void
    {
        $appClient = static::getContainer()->get('shopware.app_system.guzzle');
        $webhookManager = static::getContainer()->get(WebhookManager::class);

        $client = (new \ReflectionProperty(WebhookManager::class, 'guzzle'))->getValue($webhookManager);

        static::assertSame($appClient, $client);
    }
}
