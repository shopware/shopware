<?php declare(strict_types=1);

namespace Shopware\Framework\Test\Routing;

use Shopware\Framework\Test\Api\ApiTestCase;

class ApplicationAuthenticatorTest extends ApiTestCase
{
    public function testNoAccessWithoutApplicationToken()
    {
        $client = self::createClient(
            ['test_case' => 'ApiTest'],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => ['application/json'],
            ]
        );

        $client->request('GET', '/storefront-api/checkout');

        self::assertSame(401, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('errors', $content);
        $this->assertNotEmpty($content['errors']);

        $error = array_shift($content['errors']);

        $this->assertEquals($error['detail'], 'Header "X-SW-Application-Token" is required.');
    }

    public function testNoAccessWithUnknownApplicationToken()
    {
        $client = self::createClient(
            ['test_case' => 'ApiTest'],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => ['application/json'],
                'HTTP_X_SW_APPLICATION_TOKEN' => 'ffffffff-ffff-ffff-ffff-ffffffffffff',
            ]
        );

        $client->request('GET', '/storefront-api/checkout');

        self::assertSame(401, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('errors', $content);
        $this->assertNotEmpty($content['errors']);

        $error = array_shift($content['errors']);

        $this->assertEquals($error['detail'], 'No application found for provided token.');
    }
}
