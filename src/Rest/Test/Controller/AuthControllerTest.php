<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller;

use Shopware\Rest\Test\ApiTestCase;

class AuthControllerTest extends ApiTestCase
{
    public function testRequiresAuthentication(): void
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_Authorization', null);
        $client->request('GET', '/api/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('Please provide a valid token.', $response['errors'][0]['detail']);
    }

    public function testCreateTokenWithInvalidCredentials(): void
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
        $this->assertEquals('Bad credentials, please verify that your username/password are correctly set.', $response['errors'][0]['detail']);
    }

    public function testAccessWithInvalidToken(): void
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_Authorization', 'Bearer invalid_token_provided');
        $client->request('GET', '/api/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('Your token is invalid, please request a new one.', $response['errors'][0]['detail']);
    }

    public function testAccessWithExpiredToken(): void
    {
        $client = $this->getClient();
        $client->setServerParameter('HTTP_Authorization', 'Bearer eyJhbGciOiJSUzI1NiJ9.eyJyb2xlcyI6WyJJU19BVVRIRU5USUNBVEVEX0ZVTExZIiwiUk9MRV9BRE1JTiJdLCJ1c2VybmFtZSI6ImFkbWluIiwiaWF0IjoxNTE1NTk5NTY5LCJleHAiOjE1MTU2MDMxNjl9.v01Mulx6UyoJA84s8ZcJlP7OO8tNPunBWd6YOtvgx2kSkUap7iVz5HeM1SEQbQfrdJCAWm4v-J153IdG2XhN4GwmPlTpVtaHvoCp-yR8Dz-i_FAhJtKP4wK5eqgqAOFuGzs6DL_bv_KdFaU9YSWg57EThShAuB1M0ZuCae0lHLJJ6bxprgpJnGSniPI41FPKP2BX8pIteQgXA0fikgmWRh14hilnEYAL2P_UhB--nkaS7_52M4GCP4FAXiIfWMJxuyRC1dnd4u5n9SsbclDivPfZ4vdkULFjQfIiQEBfPuEFwXz_S0W3qYQ4ScY_l6HE6pYrlDJMf78h_yty16AZLw');
        $client->request('GET', '/api/tax');

        $this->assertEquals(401, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('errors', $response);
        $this->assertCount(1, $response['errors']);
        $this->assertEquals(401, $response['errors'][0]['status']);
        $this->assertEquals('Your token is expired, please renew it.', $response['errors'][0]['detail']);
    }

    public function testAccessProtectedResourceWithToken(): void
    {
        $client = $this->getClient();
        $client->request('GET', '/api/tax');

        $this->assertEquals(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayNotHasKey('errors', $response);
    }
}
