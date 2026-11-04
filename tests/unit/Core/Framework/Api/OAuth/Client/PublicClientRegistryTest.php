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
            'local-app' => [
                'name' => 'Local App',
                'redirect_uris' => ['http://localhost:8080/callback'],
            ],
            'insecure-app' => [
                'name' => 'Insecure App',
                'redirect_uris' => ['http://insecure.example/callback', 'com.example.app:/callback'],
            ],
        ], 'prod');
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
        yield 'registered localhost uri with its port is allowed' => ['local-app', 'http://localhost:8080/callback', true];
        yield 'registered localhost uri with another port is rejected' => ['local-app', 'http://localhost:9090/callback', false];
        yield 'registered plain http uri on a public host is rejected' => ['insecure-app', 'http://insecure.example/callback', false];
        yield 'registered private-use scheme uri is allowed' => ['insecure-app', 'com.example.app:/callback', true];
    }

    #[DataProvider('transportProvider')]
    public function testIsTransportAllowed(string $environment, string $redirectUri, bool $expected): void
    {
        $registry = new PublicClientRegistry([], $environment);

        static::assertSame($expected, $registry->isTransportAllowed($redirectUri));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function transportProvider(): iterable
    {
        yield 'https is allowed' => ['prod', 'https://my-app.example/callback', true];
        yield 'https in upper case is allowed' => ['prod', 'HTTPS://my-app.example/callback', true];
        yield 'http on localhost is allowed' => ['prod', 'http://localhost:8080/callback', true];
        yield 'http on localhost in upper case is allowed' => ['prod', 'http://LocalHost/callback', true];
        yield 'http on the IPv4 loopback literal is allowed' => ['prod', 'http://127.0.0.1:54321/callback', true];
        yield 'http on the IPv6 loopback literal is allowed' => ['prod', 'http://[::1]:54321/callback', true];
        yield 'http on a public host is rejected' => ['prod', 'http://my-app.example/callback', false];
        yield 'http on a localhost lookalike is rejected' => ['prod', 'http://localhost.example/callback', false];
        yield 'http on a public host is rejected in the test environment' => ['test', 'http://my-app.example/callback', false];
        yield 'http on a public host is allowed in the dev environment' => ['dev', 'http://shop.test/callback', true];
        yield 'http in upper case on a public host is rejected' => ['prod', 'HTTP://my-app.example/callback', false];
        yield 'private-use scheme of a native app is allowed' => ['prod', 'cursor://anysphere.cursor-retrieval/oauth/callback', true];
        yield 'reverse domain private-use scheme is allowed' => ['prod', 'com.example.app:/callback', true];
        yield 'uri without scheme is rejected' => ['prod', '//my-app.example/callback', false];
    }
}
