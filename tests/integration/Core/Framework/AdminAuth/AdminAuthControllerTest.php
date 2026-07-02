<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\AdminAuth;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\Oidc\StateService;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class AdminAuthControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testMethodsEndpointIsNotAvailableWithoutTheFeature(): void
    {
        Feature::skipTestIfActive('ADMIN_AUTH', $this);

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/methods');

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
    }

    public function testMethodsEndpointListsPasswordAndOidcProviders(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $providerId = $this->insertProvider('Corporate SSO');

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/methods');

        $response = $browser->getResponse();
        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['managedByConfig']);
        static::assertTrue($data['adminUiEnabled']);

        $byId = [];
        foreach ($data['methods'] as $method) {
            $byId[$method['id']] = $method;
        }

        static::assertArrayHasKey('password', $byId);
        static::assertSame('password', $byId['password']['type']);
        static::assertNull($byId['password']['startUrl']);

        static::assertArrayHasKey('webauthn', $byId);
        static::assertSame('webauthn', $byId['webauthn']['type']);
        static::assertNull($byId['webauthn']['startUrl']);

        static::assertArrayHasKey($providerId, $byId);
        static::assertSame('oidc', $byId[$providerId]['type']);
        static::assertSame('Corporate SSO', $byId[$providerId]['label']);
        static::assertIsString($byId[$providerId]['startUrl']);
        static::assertStringContainsString('/api/_action/admin-auth/oidc/' . $providerId . '/start', $byId[$providerId]['startUrl']);
    }

    public function testMethodsEndpointOmitsInactiveProviders(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $providerId = $this->insertProvider('Inactive SSO', active: false);

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/methods');

        $response = $browser->getResponse();
        static::assertNotFalse($response->getContent());
        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotContains($providerId, array_column($data['methods'], 'id'));
    }

    public function testWebauthnLoginOptionsReturnsOptionsAndChallengeToken(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_POST, '/api/_action/admin-auth/webauthn/login-options');

        $response = $browser->getResponse();
        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($data['options']);
        static::assertNotEmpty($data['options']['challenge']);
        static::assertSame([], $data['options']['allowCredentials'], 'discoverable login must not enumerate credentials');
        static::assertIsString($data['challengeToken']);
        static::assertStringContainsString('.', $data['challengeToken'], 'the challenge token must be signed');
    }

    public function testWebauthnLoginOptionsIsRejectedWhenTheMethodIsDisabled(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        static::getContainer()->get(SystemConfigService::class)->set(
            'core.adminAuth.methods',
            ['webauthn' => ['enabled' => false]]
        );

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_POST, '/api/_action/admin-auth/webauthn/login-options');

        static::assertSame(Response::HTTP_BAD_REQUEST, $browser->getResponse()->getStatusCode());
    }

    public function testOidcStartRedirectsToTheProviderWithStateAndNonce(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $providerId = $this->insertProvider('Corporate SSO');

        $browser = $this->getBrowser();
        $browser->followRedirects(false);
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/oidc/' . $providerId . '/start');

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());

        $location = (string) $response->headers->get('Location');
        static::assertStringStartsWith('https://idp.invalid/authorize?', $location);

        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        static::assertSame('code', $query['response_type']);
        static::assertSame('the-client-id', $query['client_id']);
        static::assertSame('openid email', $query['scope']);
        static::assertIsString($query['state']);
        static::assertNotSame('', $query['state']);
        static::assertIsString($query['nonce']);
        static::assertNotSame('', $query['nonce']);
        static::assertIsString($query['redirect_uri']);
        static::assertStringContainsString('/api/_action/admin-auth/oidc/' . $providerId . '/callback', $query['redirect_uri']);

        $stateCookie = null;
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === StateService::COOKIE_NAME) {
                $stateCookie = $cookie;
            }
        }

        static::assertNotNull($stateCookie, 'the start redirect must set the signed state cookie');
        static::assertSame('/api/_action/admin-auth', $stateCookie->getPath());
        static::assertTrue($stateCookie->isHttpOnly());
    }

    public function testOidcStartWithUnknownProviderReturnsNotFound(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->getBrowser();
        $browser->followRedirects(false);
        $browser->request(Request::METHOD_GET, '/api/_action/admin-auth/oidc/' . Uuid::randomHex() . '/start');

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode());
    }

    public function testOidcCallbackWithInvalidStateRedirectsToTheLoginScreen(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $providerId = $this->insertProvider('Corporate SSO');

        $browser = $this->getBrowser();
        $browser->followRedirects(false);
        $browser->getCookieJar()->set(new BrowserKitCookie(StateService::COOKIE_NAME, 'garbage.signature'));
        $browser->request(
            Request::METHOD_GET,
            '/api/_action/admin-auth/oidc/' . $providerId . '/callback?state=whatever&code=abc'
        );

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertStringContainsString('#/login?ssoError=invalid-state', (string) $response->headers->get('Location'));
    }

    public function testProviderSecretIsEncryptedAtRestAndStrippedFromApiReads(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $providerId = Uuid::randomHex();

        $browser = $this->getBrowser();
        $browser->request(
            Request::METHOD_POST,
            '/api/admin-auth-provider',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'id' => $providerId,
                'name' => 'Secret SSO',
                'type' => 'oidc',
                'active' => true,
                'isPrimary' => true,
                'config' => [
                    'clientId' => 'the-client-id',
                    'clientSecret' => 'super-secret-value',
                ],
            ], \JSON_THROW_ON_ERROR)
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        // The stored secret must be encrypted, not plaintext.
        $storedConfig = $this->connection->fetchOne(
            'SELECT config FROM admin_auth_provider WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($providerId)]
        );
        static::assertIsString($storedConfig);
        $stored = json_decode($storedConfig, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsString($stored['clientSecret']);
        static::assertNotSame('super-secret-value', $stored['clientSecret']);

        $encryptor = new SecretEncryptor((string) static::getContainer()->getParameter('kernel.secret'));
        static::assertSame('super-secret-value', $encryptor->decrypt($stored['clientSecret']));

        // The Admin API read must not expose the secret in any form.
        $browser->request(Request::METHOD_GET, '/api/admin-auth-provider/' . $providerId);
        $response = $browser->getResponse();
        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $config = $data['data']['attributes']['config'] ?? null;
        static::assertIsArray($config);
        static::assertSame('the-client-id', $config['clientId']);
        static::assertArrayNotHasKey('clientSecret', $config);
    }

    public function testProviderUpdateWithoutSecretKeepsTheStoredSecret(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $providerId = $this->insertProvider('Update SSO', clientSecret: 'initial-secret');

        $browser = $this->getBrowser();
        $browser->request(
            Request::METHOD_PATCH,
            '/api/admin-auth-provider/' . $providerId,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode([
                'config' => [
                    'clientId' => 'changed-client-id',
                ],
            ], \JSON_THROW_ON_ERROR)
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $storedConfig = $this->connection->fetchOne(
            'SELECT config FROM admin_auth_provider WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($providerId)]
        );
        static::assertIsString($storedConfig);
        $stored = json_decode($storedConfig, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('changed-client-id', $stored['clientId']);

        $encryptor = new SecretEncryptor((string) static::getContainer()->getParameter('kernel.secret'));
        static::assertIsString($stored['clientSecret'] ?? null, 'the stored secret must survive a config update without a new secret');
        static::assertSame('initial-secret', $encryptor->decrypt($stored['clientSecret']));
    }

    public function testDiscoverRejectsAnInvalidIssuer(): void
    {
        Feature::skipTestIfInActive('ADMIN_AUTH', $this);

        $browser = $this->getBrowser();
        $browser->request(Request::METHOD_POST, '/api/_action/admin-auth/oidc/discover', ['issuer' => 'not-a-url']);

        static::assertSame(Response::HTTP_BAD_REQUEST, $browser->getResponse()->getStatusCode());
    }

    private function insertProvider(string $name, bool $active = true, ?string $clientSecret = null): string
    {
        $id = Uuid::randomHex();

        $config = [
            'clientId' => 'the-client-id',
            'authorizationEndpoint' => 'https://idp.invalid/authorize',
            'tokenEndpoint' => 'https://idp.invalid/token',
            'jwksUri' => 'https://idp.invalid/jwks',
            'scopes' => ['openid', 'email'],
        ];

        if ($clientSecret !== null) {
            $encryptor = new SecretEncryptor((string) static::getContainer()->getParameter('kernel.secret'));
            $config['clientSecret'] = $encryptor->encrypt($clientSecret);
        }

        $this->connection->insert('admin_auth_provider', [
            'id' => Uuid::fromHexToBytes($id),
            'name' => $name,
            'type' => 'oidc',
            'active' => $active ? 1 : 0,
            'is_primary' => 1,
            'config' => json_encode($config, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
        ]);

        return $id;
    }
}
