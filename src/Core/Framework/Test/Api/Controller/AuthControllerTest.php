<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group slow
 */
class AuthControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use AppSystemTestBehaviour;

    public function testRequiresAuthentication(): void
    {
        $client = $this->getBrowser();
        $client->setServerParameter('HTTP_Authorization', '');
        $client->request('GET', '/api/tax');

        static::assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $response['errors'][0]['status']);
        static::assertEquals(
            'The resource owner or authorization server denied the request.',
            $response['errors'][0]['title']
        );
        static::assertEquals('The JWT string must have two dots', $response['errors'][0]['detail']);
    }

    public function testCreateTokenWithInvalidCredentials(): void
    {
        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'shopware',
            'password' => 'not_a_real_password',
        ];

        $client = $this->getBrowser();
        $client->request('POST', '/api/oauth/token', $authPayload);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals(Response::HTTP_BAD_REQUEST, $response['errors'][0]['status']);

        // invalid credentials should throw invalid_grant message, as by OAuth 2 specs
        // see https://github.com/thephpleague/oauth2-server/pull/967
        static::assertEquals(OAuthServerException::invalidGrant()->getMessage(), $response['errors'][0]['title']);
    }

    public function testAccessWithInvalidToken(): void
    {
        $client = $this->getBrowser();
        $client->setServerParameters([
            'HTTP_Authorization' => 'Bearer invalid_token_provided',
        ]);
        $client->request('GET', '/api/tax');

        static::assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $response['errors'][0]['status']);
        static::assertEquals(
            'The resource owner or authorization server denied the request.',
            $response['errors'][0]['title']
        );
        static::assertEquals('The JWT string must have two dots', $response['errors'][0]['detail']);
    }

    public function testAccessWithExpiredToken(): void
    {
        $client = $this->getBrowser();
        $client->setServerParameter(
            'HTTP_Authorization',
            'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjBkZmFhOTJkMWNkYTJiZmUyNGMwOGU4MmNhZmExMDY4N2I2ZWEzZTI0MjE4NjcxMmM0YjI3NTA4Y2NjNWQ0MzI3MWQxODYzODA1NDYwYzQ0In0.eyJhdWQiOiJhZG1pbmlzdHJhdGlvbiIsImp0aSI6IjBkZmFhOTJkMWNkYTJiZmUyNGMwOGU4MmNhZmExMDY4N2I2ZWEzZTI0MjE4NjcxMmM0YjI3NTA4Y2NjNWQ0MzI3MWQxODYzODA1NDYwYzQ0IiwiaWF0IjoxNTI5NDM2MTkyLCJuYmYiOjE1Mjk0MzYxOTIsImV4cCI6MTUyOTQzOTc5Miwic3ViIjoiNzI2MWQyNmMzZTM2NDUxMDk1YWZhN2MwNWY4NzMyYjUiLCJzY29wZXMiOlsid3JpdGUiLCJ3cml0ZSJdfQ.DBYbAWNpwxGL6QngLidboGbr2nmlAwjYcJIqN02sRnZNNFexy9V6uyQQ-8cJ00anwxKhqBovTzHxtXBMhZ47Ix72hxNWLjauKxQlsHAbgIKBDRbJO7QxgOU8gUnSQiXzRzKoX6XBOSHXFSUJ239lF4wai7621aCNFyEvlwf1JZVILsLjVkyIBhvuuwyIPbpEETui19BBaJ0eQZtjXtpzjsWNq1ibUCQvurLACnNxmXIj8xkSNenoX5B4p3R1gbDFuxaNHkGgsrQTwkDtmZxqCb3_0AgFL3XX0mpO5xsIJAI_hLHDPvv5m0lTQgMRrlgNdfE7ecI4GLHMkDmjWoNx_A'
        );
        $client->request('GET', '/api/tax');

        static::assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $response['errors'][0]['status']);
    }

    public function testAccessProtectedResourceWithToken(): void
    {
        $this->getBrowser()->request('GET', '/api/tax');

        static::assertEquals(
            Response::HTTP_OK,
            $this->getBrowser()->getResponse()->getStatusCode(),
            $this->getBrowser()->getResponse()->getContent()
        );

        $response = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertArrayNotHasKey('errors', $response);
    }

    public function testInvalidRefreshToken(): void
    {
        $client = $this->getBrowser();

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => 'foobar',
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload);
        $response = json_decode($client->getResponse()->getContent(), true);

        static::assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $client->getResponse()->getStatusCode(),
            print_r($client->getResponse()->getContent(), true)
        );
        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $response['errors'][0]['status']);
        static::assertEquals('The refresh token is invalid.', $response['errors'][0]['title']);
        static::assertEquals('Cannot decrypt the refresh token', $response['errors'][0]['detail']);
    }

    public function testRevokedRefreshToken(): void
    {
        $client = $this->getBrowser(false);

        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware',
            'scopes' => [],
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);
        $oldRefreshToken = json_decode($client->getResponse()->getContent(), true)['refresh_token'];

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => $oldRefreshToken,
        ];

        // old refresh token should be invalidated here, as we issue a new refresh token with it
        $client->request('POST', '/api/oauth/token', $refreshPayload);

        // try to request a new token with the old refresh token again
        $client->request('POST', '/api/oauth/token', $refreshPayload);
        $response = json_decode($client->getResponse()->getContent(), true);

        static::assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $client->getResponse()->getStatusCode(),
            print_r($client->getResponse()->getContent(), true)
        );

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $response['errors'][0]['status']);
        static::assertEquals('The refresh token is invalid.', $response['errors'][0]['title']);
        static::assertEquals('Token has been revoked', $response['errors'][0]['detail']);
    }

    public function testRefreshToken(): void
    {
        $client = $this->getBrowser(false);

        $username = Uuid::randomHex();
        $password = Uuid::randomHex();

        $this->getContainer()->get(Connection::class)->insert('user', [
            'id' => Uuid::randomBytes(),
            'first_name' => $username,
            'last_name' => '',
            'email' => 'test@example.com',
            'username' => $username,
            'password' => password_hash($password, \PASSWORD_BCRYPT),
            'locale_id' => Uuid::fromHexToBytes($this->getLocaleIdOfSystemLanguage()),
            'active' => 1,
            'admin' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->apiUsernames[] = $username;

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $username,
            'password' => $password,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode($client->getResponse()->getContent(), true);

        static::assertArrayHasKey(
            'access_token',
            $data,
            'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );
        static::assertArrayHasKey(
            'refresh_token',
            $data,
            'No refresh_token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );

        /**
         * Issue new token with the refresh_token
         */
        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => $data['refresh_token'],
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload);
        $data = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey(
            'access_token',
            $data,
            'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );
        static::assertArrayHasKey(
            'refresh_token',
            $data,
            'No refresh_token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );

        /*
         * Try access with new token
         */
        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
        $client->request('GET', '/api/tax');

        static::assertEquals(
            Response::HTTP_OK,
            $this->getBrowser()->getResponse()->getStatusCode(),
            $this->getBrowser()->getResponse()->getContent()
        );
        $response = json_decode($this->getBrowser()->getResponse()->getContent(), true);
        static::assertArrayNotHasKey('errors', $response);
    }

    public function testDefaultAccessTokenScopes(): void
    {
        $client = $this->getBrowser(false);
        $configuration = $this->getContainer()->get('shopware.jwt_config');
        $jwtTokenParser = $configuration->parser();

        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware',
            'scope' => [],
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode($client->getResponse()->getContent(), true);
        $parsedAccessToken = $jwtTokenParser->parse($data['access_token']);
        $accessTokenScopes = $parsedAccessToken->claims()->get('scopes');

        static::assertEqualsCanonicalizing(['admin', 'write'], $accessTokenScopes);
    }

    public function testUniqueAccessTokenScopes(): void
    {
        $client = $this->getBrowser(false);
        $configuration = $this->getContainer()->get('shopware.jwt_config');
        $jwtTokenParser = $configuration->parser();

        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware',
            'scope' => ['admin', 'write', 'admin', 'admin', 'write', 'write', 'admin'],
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode($client->getResponse()->getContent(), true);
        $parsedAccessToken = $jwtTokenParser->parse($data['access_token']);
        $accessTokenScopes = $parsedAccessToken->claims()->get('scopes');

        static::assertEqualsCanonicalizing(['admin', 'write'], $accessTokenScopes);
    }

    public function testAccessTokenScopesChangedAfterRefreshGrant(): void
    {
        $client = $this->getBrowser(false);
        $configuration = $this->getContainer()->get('shopware.jwt_config');
        $jwtTokenParser = $configuration->parser();

        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware',
            'scope' => ['admin', 'write'],
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);
        $data = json_decode($client->getResponse()->getContent(), true);

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => $data['refresh_token'],
            'scope' => ['admin'], // change the scope to something different
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload);
        $data = json_decode($client->getResponse()->getContent(), true);
        $scopes = $jwtTokenParser->parse($data['access_token'])->claims()->get('scopes');

        static::assertEquals(['admin'], $scopes);
    }

    public function testSuperAdminScopeRemovedOnRefreshToken(): void
    {
        $client = $this->getBrowser(false);
        $configuration = $this->getContainer()->get('shopware.jwt_config');
        $jwtTokenParser = $configuration->parser();

        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware',
            'scope' => ['admin', 'write', UserVerifiedScope::IDENTIFIER],
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode($client->getResponse()->getContent(), true);
        $parsedOldAccessToken = $jwtTokenParser->parse($data['access_token']);
        $oldAccessTokenScopes = $parsedOldAccessToken->claims()->get('scopes');

        static::assertContains(UserVerifiedScope::IDENTIFIER, $oldAccessTokenScopes);

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => $data['refresh_token'],
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload);

        $data = json_decode($client->getResponse()->getContent(), true);
        $parsedNewAccessToken = $jwtTokenParser->parse($data['access_token']);
        $newAccessTokenScopes = $parsedNewAccessToken->claims()->get('scopes');

        static::assertContains(UserVerifiedScope::IDENTIFIER, $newAccessTokenScopes);
    }

    public function testAccessTokenScopesUnchangedAfterRefreshGrant(): void
    {
        $client = $this->getBrowser(false);
        $configuration = $this->getContainer()->get('shopware.jwt_config');
        $jwtTokenParser = $configuration->parser();

        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'admin',
            'password' => 'shopware',
            'scope' => ['admin', 'write'],
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        $data = json_decode($client->getResponse()->getContent(), true);
        $parsedOldAccessToken = $jwtTokenParser->parse($data['access_token']);
        $oldAccessTokenScopes = $parsedOldAccessToken->claims()->get('scopes');

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => $data['refresh_token'],
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload);

        $data = json_decode($client->getResponse()->getContent(), true);
        $parsedNewAccessToken = $jwtTokenParser->parse($data['access_token']);
        $newAccessTokenScopes = $parsedNewAccessToken->claims()->get('scopes');

        static::assertEqualsCanonicalizing($oldAccessTokenScopes, $newAccessTokenScopes);
    }

    public function testIntegrationAuth(): void
    {
        $client = $this->getBrowser(false);

        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        $this->getContainer()->get(Connection::class)->insert('integration', [
            'id' => Uuid::randomBytes(),
            'label' => 'test integration',
            'access_key' => $accessKey,
            'secret_access_key' => password_hash($secretKey, \PASSWORD_BCRYPT),
            'write_access' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretKey,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);
        $data = json_decode($client->getResponse()->getContent(), true);

        static::assertArrayHasKey(
            'access_token',
            $data,
            'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );

        /*
         * Access protected routes
         */
        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
        $client->request('GET', '/api/tax');

        static::assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testIntegrationAuthInvalid(): void
    {
        $client = $this->getBrowser(false);

        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretKey,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        static::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testIntegrationAuthInvalidIdentifier(): void
    {
        $client = $this->getBrowser(false);

        $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretKey,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        static::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testUserWithInvalidIdentifier(): void
    {
        $client = $this->getBrowser(false);

        $accessKey = AccessKeyHelper::generateAccessKey('user');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretKey,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        static::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testUserWithLogin(): void
    {
        $client = $this->getBrowser(false);

        $user = TestUser::createNewTestUser($this->getContainer()->get(Connection::class));

        $accessKey = AccessKeyHelper::generateAccessKey('user');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        $data = [
            'userId' => $user->getUserId(),
            'writeAccess' => true,
            'accessKey' => $accessKey,
            'secretAccessKey' => $secretKey,
        ];

        $this->getContainer()->get('user_access_key.repository')
            ->create([$data], Context::createDefaultContext());

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretKey,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $data = json_decode($client->getResponse()->getContent(), true);

        static::assertArrayHasKey(
            'access_token',
            $data,
            'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );
    }

    public function testInvalidGrant(): void
    {
        $client = $this->getBrowser(false);

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'foo',
        ];

        $client->request('POST', '/api/oauth/token', $authPayload);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    public function testLoginFailsForInactiveApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../App/Manifest/_fixtures/test', false);

        $browser = $this->createClient();
        $app = $this->fetchApp('test');

        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secret = AccessKeyHelper::generateSecretAccessKey();

        $this->setAccessTokenForIntegration($app->getIntegrationId(), $accessKey, $secret);

        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secret,
        ];

        $browser->request('POST', '/api/oauth/token', $authPayload);
        static::assertEquals(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode());
    }

    public function testUnauthorizedWithPasswordGrantTypeWhenTokenExpired(): void
    {
        $browser = $this->getBrowser();

        $connection = $browser->getContainer()->get(Connection::class);
        $admin = TestUser::createNewTestUser($connection, ['product:read']);

        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $admin->getName(),
            'password' => $admin->getPassword(),
        ];

        $browser->request('POST', '/api/oauth/token', $authPayload);

        static::assertEquals(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        $token = json_decode($browser->getResponse()->getContent(), true);

        static::assertNotEmpty($accessToken = $token['access_token']);

        $userRepository = $this->getContainer()->get('user.repository');

        // Change user password
        $userRepository->update([[
            'id' => $admin->getUserId(),
            'password' => Uuid::randomHex(),
        ]], Context::createDefaultContext());

        $browser->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $accessToken));
        $browser->request('GET', '/api/tax');
        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
    }

    private function fetchApp(string $appName): ?AppEntity
    {
        /** @var EntityRepositoryInterface $appRepository */
        $appRepository = $this->getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        return $appRepository->search($criteria, Context::createDefaultContext())->first();
    }

    private function setAccessTokenForIntegration(string $integrationId, string $accessKey, string $secret): void
    {
        /** @var EntityRepositoryInterface $integrationRepository */
        $integrationRepository = $this->getContainer()->get('integration.repository');

        $integrationRepository->update([
            [
                'id' => $integrationId,
                'accessKey' => $accessKey,
                'secretAccessKey' => $secret,
            ],
        ], Context::createDefaultContext());
    }
}
