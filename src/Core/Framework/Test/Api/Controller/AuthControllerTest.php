<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;

class AuthControllerTest extends ApiTestCase
{
    public function testRequiresAuthentication(): void
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_Authorization', null);
        $client->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('Header "x-sw-access-key" is required.', $response['errors'][0]['detail']);
    }

    public function testCreateTokenWithInvalidCredentials(): void
    {
        $authPayload = json_encode([
            'username' => 'shopware',
            'password' => 'not_a_real_password',
        ]);

        $client = $this->getClient();
        $client->request('POST', '/api/v1/auth', [], [], [], $authPayload);

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('Invalid username and/or password.', $response['errors'][0]['detail']);
    }

    public function testAccessWithInvalidToken(): void
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_Authorization', 'Bearer invalid_token_provided');
        $client->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('Wrong number of segments', $response['errors'][0]['detail']);
    }

    public function testAccessWithExpiredToken(): void
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_Authorization', 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VybmFtZSI6ImFkbWluIiwiaWF0IjoxNTIzMzcwMjc0LCJuYmYiOjE1MjMzNzAyNzQsImV4cCI6MTUyMzM3Mzg3NH0.gyX-FVjyv_nIcRGryeBqk3LtXA0ZhKHOVYq_1YmWi9I');
        $client->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('Signature verification failed', $response['errors'][0]['detail']);
    }

    public function testAccessProtectedResourceWithToken(): void
    {
        $this->apiClient->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/tax');

        $this->assertEquals(200, $this->apiClient->getResponse()->getStatusCode(), $this->apiClient->getResponse()->getContent());

        $response = json_decode($this->apiClient->getResponse()->getContent(), true);

        $this->assertArrayNotHasKey('errors', $response);
    }
}
