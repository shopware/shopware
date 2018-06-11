<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\Api\ApiTestCase;

class TouchpointAuthenticatorTest extends ApiTestCase
{
    public function testNoAccessWithoutTouchpointToken()
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

        $this->assertEquals($error['detail'], 'Header "X-SW-Touchpoint-Token" is required.');
    }

    public function testNoAccessWithUnknownTouchpointToken()
    {
        $client = self::createClient(
            ['test_case' => 'ApiTest'],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => ['application/json'],
                'HTTP_X_SW_TOUCHPOINT_TOKEN' => Defaults::TOUCHPOINT,
            ]
        );

        $client->request('GET', '/storefront-api/checkout');

        self::assertSame(401, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('errors', $content);
        $this->assertNotEmpty($content['errors']);

        $error = array_shift($content['errors']);

        $this->assertEquals($error['detail'], 'No touchpoint found for provided token.');
    }
}
