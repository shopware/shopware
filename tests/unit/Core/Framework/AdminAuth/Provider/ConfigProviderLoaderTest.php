<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\Provider\ConfigProviderLoader;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ConfigProviderLoader::class)]
class ConfigProviderLoaderTest extends TestCase
{
    public function testHasProviders(): void
    {
        static::assertFalse((new ConfigProviderLoader())->hasProviders());
        static::assertFalse((new ConfigProviderLoader([]))->hasProviders());
        static::assertTrue((new ConfigProviderLoader(['okta' => ['label' => 'Okta']]))->hasProviders());
    }

    public function testLoadMapsTheFullConfigurationToTheStruct(): void
    {
        $loader = new ConfigProviderLoader([
            'corp_okta' => [
                'label' => 'Corporate SSO',
                'client_id' => 'the-client-id',
                'client_secret' => 'the-client-secret',
                'discovery_url' => 'https://idp.example.com/.well-known/openid-configuration',
                'issuer' => 'https://idp.example.com',
                'authorization_endpoint' => 'https://idp.example.com/authorize',
                'token_endpoint' => 'https://idp.example.com/token',
                'jwks_uri' => 'https://idp.example.com/jwks',
                'scopes' => ['openid', 'email'],
                'auto_provision' => true,
                'groups_claim' => 'groups',
                'role_mapping' => [
                    'idp-admins' => ['admin'],
                    'idp-catalog' => ['catalog-editor', 'media-editor'],
                ],
                'default_roles' => ['viewer'],
            ],
        ]);

        $providers = $loader->load();

        static::assertCount(1, $providers);
        $provider = $providers[0];

        static::assertSame(ConfigProviderLoader::deterministicId('corp_okta'), $provider->id);
        static::assertSame('yaml:corp_okta', $provider->providerKey);
        static::assertSame('Corporate SSO', $provider->label);
        static::assertSame('the-client-id', $provider->clientId);
        static::assertSame('the-client-secret', $provider->clientSecret);
        static::assertSame('https://idp.example.com/.well-known/openid-configuration', $provider->discoveryUrl);
        static::assertSame('https://idp.example.com', $provider->issuer);
        static::assertSame('https://idp.example.com/authorize', $provider->authorizationEndpoint);
        static::assertSame('https://idp.example.com/token', $provider->tokenEndpoint);
        static::assertSame('https://idp.example.com/jwks', $provider->jwksUri);
        static::assertSame(['openid', 'email'], $provider->scopes);
        static::assertTrue($provider->autoProvision);
        static::assertSame('groups', $provider->groupsClaim);
        static::assertSame([
            'idp-admins' => ['admin'],
            'idp-catalog' => ['catalog-editor', 'media-editor'],
        ], $provider->roleMapping);
        static::assertSame(['viewer'], $provider->defaultRoles);
        static::assertTrue($provider->active);
    }

    public function testLoadAppliesDefaultsForAMinimalConfiguration(): void
    {
        $loader = new ConfigProviderLoader([
            'keycloak' => [
                'label' => 'Keycloak',
                'client_id' => 'client',
                'client_secret' => 'secret',
            ],
        ]);

        $provider = $loader->load()[0];

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
    }

    public function testDeterministicIdIsStableAndUnique(): void
    {
        $id = ConfigProviderLoader::deterministicId('corp_okta');

        static::assertSame($id, ConfigProviderLoader::deterministicId('corp_okta'), 'the id must be stable across calls');
        static::assertNotSame($id, ConfigProviderLoader::deterministicId('other_provider'));
        static::assertTrue(Uuid::isValid($id));
        static::assertSame('5', $id[12], 'the version nibble marks the id as name-derived');
    }
}
