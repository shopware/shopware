<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends ApiTestCase
{
    public function testRequiresAuthentication(): void
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_Authorization', '');
        $client->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('The resource owner or authorization server denied the request.', $response['errors'][0]['title']);
        $this->assertEquals('The JWT string must have two dots', $response['errors'][0]['detail']);
    }

    public function testCreateTokenWithInvalidCredentials(): void
    {
        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'shopware',
            'password' => 'not_a_real_password',
        ];

        $client = $this->getClient();
        $client->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);
        $client->request('POST', '/api/oauth/token', $authPayload);

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('The user credentials were incorrect.', $response['errors'][0]['title']);
    }

    public function testAccessWithInvalidToken(): void
    {
        $client = $this->getClient();
        $client->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
            'HTTP_Authorization' => 'Bearer invalid_token_provided',
        ]);
        $client->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('The resource owner or authorization server denied the request.', $response['errors'][0]['title']);
        $this->assertEquals('The JWT string must have two dots', $response['errors'][0]['detail']);
    }

    public function testAccessWithExpiredToken(): void
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_Authorization', 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjBkZmFhOTJkMWNkYTJiZmUyNGMwOGU4MmNhZmExMDY4N2I2ZWEzZTI0MjE4NjcxMmM0YjI3NTA4Y2NjNWQ0MzI3MWQxODYzODA1NDYwYzQ0In0.eyJhdWQiOiJhZG1pbmlzdHJhdGlvbiIsImp0aSI6IjBkZmFhOTJkMWNkYTJiZmUyNGMwOGU4MmNhZmExMDY4N2I2ZWEzZTI0MjE4NjcxMmM0YjI3NTA4Y2NjNWQ0MzI3MWQxODYzODA1NDYwYzQ0IiwiaWF0IjoxNTI5NDM2MTkyLCJuYmYiOjE1Mjk0MzYxOTIsImV4cCI6MTUyOTQzOTc5Miwic3ViIjoiNzI2MWQyNmMzZTM2NDUxMDk1YWZhN2MwNWY4NzMyYjUiLCJzY29wZXMiOlsid3JpdGUiLCJ3cml0ZSJdfQ.DBYbAWNpwxGL6QngLidboGbr2nmlAwjYcJIqN02sRnZNNFexy9V6uyQQ-8cJ00anwxKhqBovTzHxtXBMhZ47Ix72hxNWLjauKxQlsHAbgIKBDRbJO7QxgOU8gUnSQiXzRzKoX6XBOSHXFSUJ239lF4wai7621aCNFyEvlwf1JZVILsLjVkyIBhvuuwyIPbpEETui19BBaJ0eQZtjXtpzjsWNq1ibUCQvurLACnNxmXIj8xkSNenoX5B4p3R1gbDFuxaNHkGgsrQTwkDtmZxqCb3_0AgFL3XX0mpO5xsIJAI_hLHDPvv5m0lTQgMRrlgNdfE7ecI4GLHMkDmjWoNx_A');
        $client->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
    }

    public function testAccessProtectedResourceWithToken(): void
    {
        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax');

        $this->assertEquals(200, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $response = json_decode($this->apiClient->getResponse()->getContent(), true);

        $this->assertArrayNotHasKey('errors', $response);
    }

    public function testInvalidRefreshToken(): void
    {
        $client = $this->getClient();
        $client->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => 'foobar',
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload);
        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), print_r($client->getResponse()->getContent(), true));
        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('The refresh token is invalid.', $response['errors'][0]['title']);
        $this->assertEquals('Cannot decrypt the refresh token', $response['errors'][0]['detail']);
    }

    public function testRefreshToken(): void
    {
        $client = $this->getClient();
        $client->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);

        $username = Uuid::uuid4()->getHex();
        $password = Uuid::uuid4()->getHex();

        self::$container->get(Connection::class)->insert('user', [
            'id' => Uuid::uuid4()->getBytes(),
            'tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'name' => $username,
            'email' => 'admin@example.com',
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'locale_id' => Uuid::fromStringToBytes('7b52d9dd-2b06-40ec-90be-9f57edf29be7'),
            'locale_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'locale_tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'active' => 1,
            'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
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

        $this->assertArrayHasKey('access_token', $data, 'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error'));
        $this->assertArrayHasKey('refresh_token', $data, 'No refresh_token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error'));

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
        $this->assertArrayHasKey('access_token', $data, 'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error'));
        $this->assertArrayHasKey('refresh_token', $data, 'No refresh_token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error'));

        /*
         * Try access with new token
         */
        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
        $client->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax');

        $this->assertEquals(200, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());
        $response = json_decode($this->apiClient->getResponse()->getContent(), true);
        $this->assertArrayNotHasKey('errors', $response);
    }

    public function testIntegrationAuth(): void
    {
        $client = $this->getClient();
        $client->setServerParameters([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);

        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        self::$container->get(Connection::class)->insert('integration', [
            'id' => Uuid::uuid4()->getBytes(),
            'tenant_id' => Uuid::fromHexToBytes(Defaults::TENANT_ID),
            'label' => 'test integration',
            'access_key' => $accessKey,
            'secret_access_key' => password_hash($secretKey, PASSWORD_BCRYPT),
            'write_access' => 1,
            'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
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

        $this->assertArrayHasKey('access_token', $data, 'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error'));

        /*
         * Access protected routes
         */
        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['access_token']));
        $client->request('GET', '/api/v1/tax');

        $this->assertEquals(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }
}
