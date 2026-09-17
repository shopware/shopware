<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth\Client;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\Client\PublicClientRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PublicClientRegistry::class)]
class PublicClientRegistryTest extends TestCase
{
    private PublicClientRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new PublicClientRegistry([
            'shopware-cli' => [
                'name' => 'Shopware CLI',
                'redirect_uris' => ['http://127.0.0.1/callback', 'http://[::1]/callback'],
            ],
            'my-app' => [
                'name' => 'My App',
                'redirect_uris' => ['https://my-app.example/oauth/callback'],
            ],
        ]);
    }

    public function testHas(): void
    {
        static::assertTrue($this->registry->has('shopware-cli'));
        static::assertTrue($this->registry->has('my-app'));
        static::assertFalse($this->registry->has('administration'));
        static::assertFalse($this->registry->has(''));
    }

    public function testGetBuildsPublicClientLimitedToAuthorizationCodeAndRefreshToken(): void
    {
        $client = $this->registry->get('shopware-cli');

        static::assertNotNull($client);
        static::assertSame('shopware-cli', $client->getIdentifier());
        static::assertSame('Shopware CLI', $client->getName());
        static::assertFalse($client->isConfidential());
        static::assertTrue($client->getWriteAccess());
        static::assertSame(['http://127.0.0.1/callback', 'http://[::1]/callback'], $client->getRedirectUri());
        static::assertTrue($client->supportsGrantType('authorization_code'));
        static::assertTrue($client->supportsGrantType('refresh_token'));
        static::assertFalse($client->supportsGrantType('password'));
        static::assertFalse($client->supportsGrantType('client_credentials'));
    }

    public function testGetReturnsNullForUnknownClient(): void
    {
        static::assertNull($this->registry->get('unknown'));
        static::assertNull($this->registry->get(''));
    }

    #[DataProvider('redirectUriProvider')]
    public function testIsRedirectUriAllowed(string $clientId, string $redirectUri, bool $expected): void
    {
        static::assertSame($expected, $this->registry->isRedirectUriAllowed($clientId, $redirectUri));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function redirectUriProvider(): iterable
    {
        yield 'loopback IPv4 with any port is allowed' => ['shopware-cli', 'http://127.0.0.1:54321/callback', true];
        yield 'loopback IPv4 without port is allowed' => ['shopware-cli', 'http://127.0.0.1/callback', true];
        yield 'loopback IPv6 with any port is allowed' => ['shopware-cli', 'http://[::1]:54321/callback', true];
        yield 'localhost is not a loopback literal and is rejected' => ['shopware-cli', 'http://localhost:54321/callback', false];
        yield 'loopback with different path is rejected' => ['shopware-cli', 'http://127.0.0.1:54321/other', false];
        yield 'loopback with https scheme is rejected' => ['shopware-cli', 'https://127.0.0.1:54321/callback', false];
        yield 'foreign host is rejected' => ['shopware-cli', 'http://evil.example/callback', false];
        yield 'non loopback uri must match exactly' => ['my-app', 'https://my-app.example/oauth/callback', true];
        yield 'non loopback uri with other port is rejected' => ['my-app', 'https://my-app.example:8443/oauth/callback', false];
        yield 'unknown client is rejected' => ['unknown', 'http://127.0.0.1:54321/callback', false];
    }
}
