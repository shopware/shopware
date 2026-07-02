<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\Provider\AdminAuthProvider;

/**
 * @internal
 */
#[CoversClass(AdminAuthProvider::class)]
class AdminAuthProviderTest extends TestCase
{
    public function testMinimalProviderAppliesTheDocumentedDefaults(): void
    {
        $provider = new AdminAuthProvider(
            id: 'a5b4885a89694a4c8e28e00b48b09dcc',
            providerKey: 'yaml:corp_okta',
            label: 'Corporate SSO',
            clientId: 'client-id',
            clientSecret: 'client-secret',
        );

        static::assertNull($provider->discoveryUrl);
        static::assertNull($provider->issuer);
        static::assertNull($provider->authorizationEndpoint);
        static::assertNull($provider->tokenEndpoint);
        static::assertNull($provider->jwksUri);
        static::assertSame(['openid', 'profile', 'email'], $provider->scopes);
        static::assertFalse($provider->autoProvision);
        static::assertNull($provider->groupsClaim);
        static::assertSame([], $provider->roleMapping);
        static::assertSame([], $provider->defaultRoles);
        static::assertTrue($provider->active);
    }

    public function testFullyConfiguredProviderKeepsEveryValue(): void
    {
        $provider = new AdminAuthProvider(
            id: 'a5b4885a89694a4c8e28e00b48b09dcc',
            providerKey: 'a5b4885a89694a4c8e28e00b48b09dcc',
            label: 'Corporate SSO',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            discoveryUrl: 'https://idp.example/.well-known/openid-configuration',
            issuer: 'https://idp.example',
            authorizationEndpoint: 'https://idp.example/authorize',
            tokenEndpoint: 'https://idp.example/token',
            jwksUri: 'https://idp.example/jwks',
            scopes: ['openid', 'email'],
            autoProvision: true,
            groupsClaim: 'groups',
            roleMapping: ['idp-admins' => ['admin']],
            defaultRoles: ['viewer'],
            active: false,
        );

        static::assertSame('a5b4885a89694a4c8e28e00b48b09dcc', $provider->id);
        static::assertSame('a5b4885a89694a4c8e28e00b48b09dcc', $provider->providerKey);
        static::assertSame('Corporate SSO', $provider->label);
        static::assertSame('client-id', $provider->clientId);
        static::assertSame('client-secret', $provider->clientSecret);
        static::assertSame('https://idp.example/.well-known/openid-configuration', $provider->discoveryUrl);
        static::assertSame('https://idp.example', $provider->issuer);
        static::assertSame('https://idp.example/authorize', $provider->authorizationEndpoint);
        static::assertSame('https://idp.example/token', $provider->tokenEndpoint);
        static::assertSame('https://idp.example/jwks', $provider->jwksUri);
        static::assertSame(['openid', 'email'], $provider->scopes);
        static::assertTrue($provider->autoProvision);
        static::assertSame('groups', $provider->groupsClaim);
        static::assertSame(['idp-admins' => ['admin']], $provider->roleMapping);
        static::assertSame(['viewer'], $provider->defaultRoles);
        static::assertFalse($provider->active);
    }
}
