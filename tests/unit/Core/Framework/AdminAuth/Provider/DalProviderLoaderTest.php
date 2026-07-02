<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Provider;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\AdminAuth\Provider\DalProviderLoader;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(DalProviderLoader::class)]
class DalProviderLoaderTest extends TestCase
{
    private SecretEncryptor $encryptor;

    protected function setUp(): void
    {
        $this->encryptor = new SecretEncryptor('test-app-secret');
    }

    public function testLoadMapsTheConfigJsonToTheStruct(): void
    {
        $id = Uuid::randomHex();

        $loader = $this->createLoader([[
            'id' => $id,
            'name' => 'Corporate SSO',
            'active' => '1',
            'config' => json_encode([
                'clientId' => 'the-client-id',
                'clientSecret' => $this->encryptor->encrypt('the-client-secret'),
                'discoveryUrl' => 'https://idp.example.com/.well-known/openid-configuration',
                'issuer' => 'https://idp.example.com',
                'authorizationEndpoint' => 'https://idp.example.com/authorize',
                'tokenEndpoint' => 'https://idp.example.com/token',
                'jwksUri' => 'https://idp.example.com/jwks',
                'scopes' => ['openid', 'email'],
                'autoProvision' => true,
                'groupsClaim' => 'groups',
                'roleMapping' => ['idp-admins' => ['admin']],
                'defaultRoles' => ['viewer'],
            ], \JSON_THROW_ON_ERROR),
        ]]);

        $providers = $loader->load();

        static::assertCount(1, $providers);
        $provider = $providers[0];

        static::assertSame($id, $provider->id);
        static::assertSame($id, $provider->providerKey, 'database providers use their hex id as provider key');
        static::assertSame('Corporate SSO', $provider->label);
        static::assertSame('the-client-id', $provider->clientId);
        static::assertSame('the-client-secret', $provider->clientSecret, 'the stored secret must be decrypted');
        static::assertSame('https://idp.example.com/.well-known/openid-configuration', $provider->discoveryUrl);
        static::assertSame('https://idp.example.com', $provider->issuer);
        static::assertSame('https://idp.example.com/authorize', $provider->authorizationEndpoint);
        static::assertSame('https://idp.example.com/token', $provider->tokenEndpoint);
        static::assertSame('https://idp.example.com/jwks', $provider->jwksUri);
        static::assertSame(['openid', 'email'], $provider->scopes);
        static::assertTrue($provider->autoProvision);
        static::assertSame('groups', $provider->groupsClaim);
        static::assertSame(['idp-admins' => ['admin']], $provider->roleMapping);
        static::assertSame(['viewer'], $provider->defaultRoles);
        static::assertTrue($provider->active);
    }

    public function testLoadHandlesMissingConfigAndInactiveRows(): void
    {
        $loader = $this->createLoader([[
            'id' => Uuid::randomHex(),
            'name' => 'Empty provider',
            'active' => '0',
            'config' => null,
        ]]);

        $provider = $loader->load()[0];

        static::assertFalse($provider->active);
        static::assertSame('', $provider->clientId);
        static::assertSame('', $provider->clientSecret);
        static::assertSame(['openid', 'profile', 'email'], $provider->scopes);
    }

    public function testLoadSupportsSpaceSeparatedScopeStrings(): void
    {
        $loader = $this->createLoader([[
            'id' => Uuid::randomHex(),
            'name' => 'Provider',
            'active' => '1',
            'config' => json_encode(['scopes' => 'openid email'], \JSON_THROW_ON_ERROR),
        ]]);

        static::assertSame(['openid', 'email'], $loader->load()[0]->scopes);
    }

    public function testLoadSkipsProvidersWithUndecryptableSecrets(): void
    {
        $loader = $this->createLoader([[
            'id' => Uuid::randomHex(),
            'name' => 'Broken provider',
            'active' => '1',
            'config' => json_encode(['clientSecret' => 'not-an-encrypted-value'], \JSON_THROW_ON_ERROR),
        ]]);

        static::assertSame([], $loader->load());
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function createLoader(array $rows): DalProviderLoader
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return new DalProviderLoader($connection, $this->encryptor, new NullLogger());
    }
}
