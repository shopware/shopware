<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller;

use Shopware\Rest\Test\ApiTestCase;

class AuthControllerTest extends ApiTestCase
{
    public function testRequiresAuthentication()
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_Authorization', null);
        $client->request('GET', '/api/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('JWT Token not found', $response['errors'][0]['title']);
    }

    public function testCreateTokenWithInvalidCredentials()
    {
        $authPayload = json_encode([
            'username' => 'shopware',
            'password' => 'not_a_real_password',
        ]);

        $client = $this->getClient();
        $client->request('POST', '/api/auth', [], [], [], $authPayload);

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('Bad credentials', $response['errors'][0]['title']);
    }

    public function testAccessWithInvalidToken()
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_Authorization', 'Bearer invalid_token_provided');
        $client->request('GET', '/api/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('Invalid JWT Token', $response['errors'][0]['title']);
    }

    public function testAccessProtectedResourceWithToken()
    {
        $client = $this->getClient();
        $client->request('GET', '/api/tax');

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayNotHasKey('errors', $response);
    }
}
