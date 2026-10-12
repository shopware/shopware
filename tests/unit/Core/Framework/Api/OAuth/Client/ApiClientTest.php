<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiClient::class)]
class ApiClientTest extends TestCase
{
    public function testDefaultsAllowEveryGrantTypeAndHaveNoRedirectUri(): void
    {
        $client = new ApiClient('administration', true, confidential: false);

        static::assertSame('administration', $client->getIdentifier());
        static::assertTrue($client->getWriteAccess());
        static::assertFalse($client->isConfidential());
        static::assertSame('', $client->getName());
        static::assertSame([], $client->getRedirectUri());
        static::assertTrue($client->supportsGrantType('password'));
        static::assertTrue($client->supportsGrantType('authorization_code'));
    }

    public function testRedirectUrisAndGrantTypesCanBeRestricted(): void
    {
        $client = new ApiClient(
            'shopware-cli',
            writeAccess: true,
            name: 'Shopware CLI',
            confidential: false,
            redirectUris: ['http://127.0.0.1/callback'],
            grantTypes: ['authorization_code'],
        );

        static::assertSame('Shopware CLI', $client->getName());
        static::assertSame(['http://127.0.0.1/callback'], $client->getRedirectUri());
        static::assertTrue($client->supportsGrantType('authorization_code'));
        static::assertFalse($client->supportsGrantType('refresh_token'));
        static::assertFalse($client->supportsGrantType('password'));
    }
}
